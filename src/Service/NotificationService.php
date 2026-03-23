<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private UserRepository $userRepository
    ) {
    }

    public function notifyAdmin(string $type, string $message, ?string $relatedEntity = null, ?int $relatedId = null): void
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT id FROM user WHERE JSON_CONTAINS(roles, :role) = 1';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('role', json_encode(['ROLE_ADMIN']));
        $result = $stmt->executeQuery();
        $adminIds = $result->fetchFirstColumn();

        foreach ($adminIds as $adminId) {
            $admin = $this->userRepository->find($adminId);
            if ($admin) {
                $this->createNotification($admin, $type, $message, $relatedEntity, $relatedId);
            }
        }
    }

    public function notifyUser(User $user, string $type, string $message, ?string $relatedEntity = null, ?int $relatedId = null): void
    {
        $this->createNotification($user, $type, $message, $relatedEntity, $relatedId);
    }

    private function createNotification(User $user, string $type, string $message, ?string $relatedEntity = null, ?int $relatedId = null): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setRelatedEntity($relatedEntity);
        $notification->setRelatedId($relatedId);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}

