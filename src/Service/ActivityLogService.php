<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ActivityLogService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Log an action with target data similar to the sample:
     * User ID, Username, Role, Action, Target Data, Date/Time.
     */
    public function logAction(?User $user, string $action, object $entity): void
    {
        $targetEntity = get_class($entity);
        $targetId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $targetData = $this->buildTargetData($entity);

        $this->logEvent($user, $action, $targetData, null, $targetEntity, $targetId !== null ? (string) $targetId : null);
    }

    /**
     * Mobile app + custom events (same activity_log table as the website).
     */
    public function logEvent(
        ?User $user,
        string $action,
        string $targetData,
        ?string $details = null,
        ?string $targetEntity = 'App\\Entity\\Mobile',
        ?string $targetId = null,
        ?string $ipAddress = null,
        ?string $platform = null,
    ): void {
        $connection = $this->em->getConnection();

        $userId = $user?->getId();
        $username = $user?->getEmail() ?? $user?->getUserIdentifier() ?? 'System';
        $role = $user && method_exists($user, 'getPrimaryRole')
            ? $user->getPrimaryRole()
            : 'ROLE_TENANT';

        try {
            $connection->executeStatement(
                'INSERT INTO activity_log (user_id, username, role, action, target_entity, target_id, target_data, details, ip_address, platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $userId,
                    $username,
                    $role,
                    strtoupper($action),
                    $targetEntity,
                    $targetId,
                    $targetData,
                    $details,
                    $ipAddress,
                    $platform,
                ]
            );
        } catch (\Throwable $e) {
            error_log('ActivityLogService failed: ' . $e->getMessage());
        }
    }

    private function buildTargetData(object $entity): string
    {
        $class = (new \ReflectionClass($entity))->getShortName();
        $name = null;

        if (method_exists($entity, 'getName') && $entity->getName()) {
            $name = $entity->getName();
        } elseif (method_exists($entity, 'getEmail') && $entity->getEmail()) {
            $name = $entity->getEmail();
        } elseif (method_exists($entity, 'getTitle') && $entity->getTitle()) {
            $name = $entity->getTitle();
        }

        $id = method_exists($entity, 'getId') ? $entity->getId() : null;

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

