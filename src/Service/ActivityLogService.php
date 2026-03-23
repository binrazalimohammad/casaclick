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
        $connection = $this->em->getConnection();

        $userId = $user?->getId();
        $username = $user?->getEmail() ?? $user?->getUserIdentifier() ?? 'System';
        $roles = $user?->getRoles() ?? [];
        if (in_array('ROLE_ADMIN', $roles)) {
            $role = 'ROLE_ADMIN';
        } elseif (in_array('ROLE_LANDLORD', $roles)) {
            $role = 'ROLE_LANDLORD';
        } else {
            $role = 'ROLE_TENANT';
        }

        $targetEntity = get_class($entity);
        $targetId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $targetData = $this->buildTargetData($entity);

        try {
            $connection->executeStatement(
                'INSERT INTO activity_log (user_id, username, role, action, target_entity, target_id, target_data, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $userId,
                    $username,
                    $role,
                    strtoupper($action),
                    $targetEntity,
                    $targetId ? (string)$targetId : null,
                    $targetData,
                    null,
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

