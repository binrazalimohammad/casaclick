<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ActivitySubscriber - Logs all entity operations to ActivityLog
 * 
 * REQUIRED ACTIONS BEING LOGGED:
 * ✅ Admin creates a user (postPersist on User entity)
 * ✅ Admin deletes a user (postRemove on User entity)
 * ✅ Admin updates any record (postUpdate on any entity)
 * ✅ Landlord creates a record (postPersist on Product, Tenant, Landlord, etc.)
 * ✅ Landlord edits a record (postUpdate on Product, Tenant, Landlord, etc.)
 * ✅ Landlord deletes a record (postRemove on Product, Tenant, Landlord, etc.)
 * 
 * Note: User login/logout are handled by SecurityEventListener
 */
class ActivitySubscriber implements EventSubscriber
{
    private TokenStorageInterface $tokenStorage;
    private EntityManagerInterface $em;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
    {
        $this->tokenStorage = $tokenStorage;
        $this->em = $em;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    private function getUser()
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }
        $user = $token->getUser();
        return is_object($user) ? $user : null;
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        // Log CREATE action for all entities (Admin, Landlord, Tenant actions)
        // This includes: Admin creating users, Landlords creating listings, etc.
        $entity = $args->getObject();
        if (!($entity instanceof ActivityLog)) {
            error_log('ActivitySubscriber: postPersist triggered for ' . get_class($entity));
            $this->log('CREATE', $args);
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        // Log UPDATE action for all entities (Admin, Landlord, Tenant actions)
        // This includes: Admin updating users, Landlords updating listings, etc.
        $entity = $args->getObject();
        if (!($entity instanceof ActivityLog)) {
            error_log('ActivitySubscriber: postUpdate triggered for ' . get_class($entity));
            $this->log('UPDATE', $args);
        }
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        // Log DELETE action - entity data is still available in postRemove
        // This includes: Admin deleting users, Landlords deleting listings, etc.
        $entity = $args->getObject();
        if (!($entity instanceof ActivityLog)) {
            error_log('ActivitySubscriber: postRemove triggered for ' . get_class($entity));
            $this->log('DELETE', $args);
        }
    }

    private function log(string $action, LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();

        // Don't log ActivityLog entities themselves (prevent infinite loop)
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Don't log Notification entities (they're system-generated)
        // Uncomment if you don't want to log notifications
        // if ($entity instanceof \App\Entity\Notification) {
        //     return;
        // }

        // Get user information
        $user = $this->getUser();
        $userId = $user ? $user->getId() : null;
        $username = $user ? ($user->getEmail() ?? $user->getName() ?? 'Unknown') : 'System';
        $role = 'SYSTEM';
        
        if ($user) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $role = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_LANDLORD', $roles)) {
                $role = 'ROLE_LANDLORD';
            } else {
                $role = 'ROLE_TENANT';
            }
        }
        
        // Get entity information
        $targetEntity = get_class($entity);
        $targetId = null;
        if (method_exists($entity, 'getId')) {
            $targetId = $entity->getId();
        }
        $targetData = $this->buildTargetData($entity);

        // Insert immediately using raw SQL to avoid nested flush issues
        try {
            $now = new \DateTimeImmutable();
            $connection = $em->getConnection();
            $connection->executeStatement(
                'INSERT INTO activity_log (user_id, username, role, action, target_entity, target_id, target_data, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $username,
                    $role,
                    $action,
                    $targetEntity,
                    $targetId ? (string)$targetId : null,
                    $targetData,
                    null,
                    $now->format('Y-m-d H:i:s'),
                ],
                [
                    \PDO::PARAM_INT,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_NULL,
                    \PDO::PARAM_STR,
                ]
            );
            error_log('✅ Activity log saved: ' . $action . ' on ' . $targetEntity . ' by ' . $username . ' | Target: ' . $targetData);
        } catch (\Exception $e) {
            error_log('❌ Activity log INSERT failed: ' . $e->getMessage() . ' | Action: ' . $action . ' | Entity: ' . $targetEntity . ' | Code: ' . $e->getCode());
            error_log('Stack trace: ' . substr($e->getTraceAsString(), 0, 300));
        }
    }

    /**
     * Helper for onFlush: derive log data directly from an entity instance.
     */
    private function queueLogFromEntity(string $action, object $entity): void
    {
        // Skip logging ActivityLog itself to avoid recursion
        if ($entity instanceof ActivityLog) {
            return;
        }

        $user = $this->getUser();
        $userId = $user ? $user->getId() : null;
        $username = $user ? ($user->getEmail() ?? $user->getName() ?? 'Unknown') : 'System';
        $role = 'SYSTEM';

        if ($user) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $role = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_LANDLORD', $roles)) {
                $role = 'ROLE_LANDLORD';
            } else {
                $role = 'ROLE_TENANT';
            }
        }

        $targetEntity = get_class($entity);
        $targetId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $targetData = $this->buildTargetData($entity);

        $this->pendingLogs[] = [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'action' => $action,
            'target_entity' => $targetEntity,
            'target_id' => $targetId ? (string)$targetId : null,
            'target_data' => $targetData,
        ];
    }

    private function createLog(string $action, object $entity): ?ActivityLog
    {
        // Don't log ActivityLog entities themselves (prevent infinite loop)
        if ($entity instanceof ActivityLog) {
            return null;
        }

        // Don't log Notification entities (they're system-generated)
        // Uncomment if you don't want to log notifications
        // if ($entity instanceof \App\Entity\Notification) {
        //     return null;
        // }

        // Get the current user performing the action
        // This will be Admin, Landlord, or Tenant depending on who is logged in
        $user = $this->getUser();

        $log = new ActivityLog();
        $log->setAction($action);
        $log->setUser($user);
        
        if ($user) {
            // Use email as username (or name if available) - matches format in image
            $username = $user->getEmail() ?? $user->getName() ?? 'Unknown';
            $log->setUsername($username);
            
            // Get primary role (not the first in array, but the actual assigned role)
            // This logs actions for Admin, Landlord, and Tenant users
            $roles = $user->getRoles();
            $primaryRole = $roles[0] ?? 'ROLE_TENANT';
            // Prioritize ROLE_ADMIN, then ROLE_LANDLORD, then ROLE_TENANT
            if (in_array('ROLE_ADMIN', $roles)) {
                $primaryRole = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_LANDLORD', $roles)) {
                $primaryRole = 'ROLE_LANDLORD';
            }
            $log->setRole($primaryRole);
        } else {
            // System action (no user logged in)
            $log->setUsername('System');
            $log->setRole('SYSTEM');
        }
        
        $log->setTargetEntity(get_class($entity));

        // Get entity ID (for DELETE, ID might still be available)
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            if ($id !== null) {
                $log->setTargetId((string)$id);
            }
        }

        // Build target data string (e.g., "Product: Laptop Asus (ID: 14)")
        $log->setTargetData($this->buildTargetData($entity));
        $log->setDetails(null);

        return $log;
    }

    private function buildTargetData(object $entity): ?string
    {
        $class = (new \ReflectionClass($entity))->getShortName();
        $name = null;
        
        // Handle different entity types with their specific naming methods
        switch ($class) {
            case 'User':
                // For User entities, prioritize email (username) over name
                if (method_exists($entity, 'getEmail') && $entity->getEmail()) {
                    $name = $entity->getEmail();
                } elseif (method_exists($entity, 'getName') && $entity->getName()) {
                    $name = $entity->getName();
                }
                break;
                
            case 'Landlord':
                // Landlord has FirstName and LastName
                $firstName = method_exists($entity, 'getFirstName') ? $entity->getFirstName() : null;
                $lastName = method_exists($entity, 'getLastName') ? $entity->getLastName() : null;
                if ($firstName && $lastName) {
                    $name = $firstName . ' ' . $lastName;
                } elseif ($firstName) {
                    $name = $firstName;
                } elseif ($lastName) {
                    $name = $lastName;
                } elseif (method_exists($entity, 'getEmail') && $entity->getEmail()) {
                    $name = $entity->getEmail();
                }
                break;
                
            case 'Tenant':
                // Tenant has name field
                if (method_exists($entity, 'getName') && $entity->getName()) {
                    $name = $entity->getName();
                } elseif (method_exists($entity, 'getEmail') && $entity->getEmail()) {
                    $name = $entity->getEmail();
                }
                break;
                
            case 'Product':
                // Product has name field
                if (method_exists($entity, 'getName') && $entity->getName()) {
                    $name = $entity->getName();
                }
                break;
                
            case 'Application':
                // Application - try to get listing name
                if (method_exists($entity, 'getListing')) {
                    $listing = $entity->getListing();
                    if ($listing && method_exists($listing, 'getName')) {
                        $name = 'Application for ' . $listing->getName();
                    }
                }
                if (!$name) {
                    $name = 'Application';
                }
                break;
                
            case 'Payment':
                // Payment - try to get amount or reference
                if (method_exists($entity, 'getAmount')) {
                    $amount = $entity->getAmount();
                    $name = 'Payment: ₱' . number_format($amount, 2);
                } elseif (method_exists($entity, 'getReference')) {
                    $name = 'Payment: ' . $entity->getReference();
                } else {
                    $name = 'Payment';
                }
                break;
                
            case 'Notification':
                // Notification - use message or type
                if (method_exists($entity, 'getMessage') && $entity->getMessage()) {
                    $message = $entity->getMessage();
                    $name = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
                } elseif (method_exists($entity, 'getType') && $entity->getType()) {
                    $name = 'Notification: ' . $entity->getType();
                } else {
                    $name = 'Notification';
                }
                break;
                
            default:
                // For other entities, try common methods
                if (method_exists($entity, 'getName') && $entity->getName()) {
                    $name = $entity->getName();
                } elseif (method_exists($entity, 'getEmail') && $entity->getEmail()) {
                    $name = $entity->getEmail();
                } elseif (method_exists($entity, 'getTitle') && $entity->getTitle()) {
                    $name = $entity->getTitle();
                }
                break;
        }

        $id = null;
        if (method_exists($entity, 'getId') && $entity->getId() !== null) {
            $id = $entity->getId();
        }

        // Format: "EntityType: Name (ID: X)" or "EntityType (ID: X)" if no name
        if ($name && $id !== null) {
            return $class . ': ' . $name . ' (ID: ' . $id . ')';
        } elseif ($id !== null) {
            return $class . ' (ID: ' . $id . ')';
        } elseif ($name) {
            return $class . ': ' . $name;
        }

        return $class;
    }
}

