<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists in-app notification history and triggers realtime + FCM broadcast via Socket.IO.
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private RealtimeBroadcastService $realtimeBroadcast,
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
                $this->notifyUser($admin, $type, $message, $relatedEntity, $relatedId);
            }
        }
    }

    public function notifyUser(
        User $user,
        string $type,
        string $message,
        ?string $relatedEntity = null,
        ?int $relatedId = null,
    ): Notification {
        $notification = $this->createNotification($user, $type, $message, $relatedEntity, $relatedId);
        $this->realtimeBroadcast->broadcastNotification($user, $notification);

        return $notification;
    }

    /**
     * Notify tenant when admin/staff changes booking (order) status — saves history + emits order_updated.
     */
    public function notifyOrderStatusChange(
        Application $application,
        string $oldStatus,
        string $newStatus,
    ): ?Notification {
        if ($oldStatus === $newStatus) {
            return null;
        }

        $tenant = $application->getTenant();
        if (!$tenant) {
            return null;
        }

        $orderId = (int) $application->getId();
        $listingName = $application->getListing()?->getName() ?? 'your listing';
        $newLower = strtolower(trim($newStatus));

        [$type, $title, $message] = match ($newLower) {
            'approved', 'accepted' => [
                'application_approved',
                'Application approved',
                sprintf('Your application for %s has been approved by your landlord.', $listingName),
            ],
            'rejected' => [
                'application_rejected',
                'Application declined',
                sprintf('Your application for %s was declined by your landlord.', $listingName),
            ],
            default => [
                'order_update',
                'Order update',
                OrderStatusLabelService::orderUpdateMessage($listingName, $newStatus),
            ],
        };

        $timestamp = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        $notification = $this->createNotification(
            $tenant,
            $type,
            $message,
            'application',
            $orderId,
        );

        $this->realtimeBroadcast->broadcastOrderUpdated($tenant, $notification, [
            'order_id' => $orderId,
            'customer_id' => (int) $tenant->getId(),
            'status' => $newStatus,
            'message' => $message,
            'timestamp' => $timestamp,
            'title' => $title,
            'statusLabel' => OrderStatusLabelService::label($newStatus),
        ]);

        return $notification;
    }

    /**
     * Notify tenant when a payment status changes (landlord approve/reject, Paymongo, etc.).
     */
    public function notifyTenantPaymentStatusChange(Payment $payment, string $newStatus): ?Notification
    {
        $application = $payment->getApplication();
        $tenant = $application?->getTenant();
        if (!$tenant) {
            return null;
        }

        $listingName = $application->getListing()?->getName() ?? 'your listing';
        $amount = number_format((float) $payment->getAmount(), 2);
        $status = strtolower(trim($newStatus));

        [$type, $message] = match (true) {
            in_array($status, ['completed', 'paid', 'received'], true) => [
                'payment_approved',
                sprintf('Your payment of ₱%s for "%s" has been approved.', $amount, $listingName),
            ],
            in_array($status, ['failed', 'rejected'], true) => [
                'payment_rejected',
                sprintf(
                    'Your payment of ₱%s for "%s" was declined. Contact your landlord if you have questions.',
                    $amount,
                    $listingName,
                ),
            ],
            default => [
                'payment_update',
                sprintf(
                    'Your payment of ₱%s for "%s" is now: %s',
                    $amount,
                    $listingName,
                    OrderStatusLabelService::label($newStatus),
                ),
            ],
        };

        return $this->notifyUser($tenant, $type, $message, 'payment', (int) $payment->getId());
    }

    private function createNotification(
        User $user,
        string $type,
        string $message,
        ?string $relatedEntity = null,
        ?int $relatedId = null,
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setRelatedEntity($relatedEntity);
        $notification->setRelatedId($relatedId);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}
