<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('notification')]
final class NotificationController extends AbstractController
{
    #[Route('/mark-read/{id}', name: 'app_notification_mark_read', methods: ['POST'])]
    public function markAsRead(int $id, NotificationRepository $notificationRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $notification = $notificationRepository->find($id);
        if (!$notification || $notification->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $notification->setIsRead(true);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/mark-all-read', name: 'app_notification_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(NotificationRepository $notificationRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $notifications = $notificationRepository->findUnreadByUser($user);
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $entityManager->flush();

        return new JsonResponse(['success' => true, 'count' => count($notifications)]);
    }

    #[Route('/count', name: 'app_notification_count', methods: ['GET'])]
    public function getUnreadCount(NotificationRepository $notificationRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['count' => 0]);
        }

        $count = $notificationRepository->countUnreadByUser($user);
        return new JsonResponse(['count' => $count]);
    }
}

