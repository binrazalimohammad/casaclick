<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pushes realtime events to the Socket.IO notification server (see BinRazali/scripts/socketio-notification-server.js).
 *
 * Symfony saves notifications to MySQL first, then POSTs here so connected mobile clients update instantly.
 * Configure WS_BROADCAST_URL and WS_INTERNAL_SECRET in .env (local: http://127.0.0.1:8082).
 */
class RealtimeBroadcastService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $broadcastUrl = '',
        private string $internalSecret = '',
    ) {
    }

    /**
     * @param array<string, mixed> $orderPayload order_updated fields: order_id, customer_id, status, message, timestamp
     */
    public function broadcastOrderUpdated(User $recipient, Notification $notification, array $orderPayload): void
    {
        $userId = (int) $recipient->getId();
        if ($userId <= 0) {
            return;
        }

        $notificationRow = [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'message' => $notification->getMessage(),
            'isRead' => $notification->isRead(),
            'relatedEntity' => $notification->getRelatedEntity(),
            'relatedId' => $notification->getRelatedId(),
            'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        $this->postBroadcast([
            'userId' => $userId,
            'event' => 'order_updated',
            'notification' => $notificationRow,
            'order' => $orderPayload,
            'message' => $orderPayload['message'] ?? $notification->getMessage(),
            'fcmToken' => $recipient->getFcmToken(),
            'fcmTitle' => $orderPayload['title'] ?? 'Order update',
            'fcmBody' => $orderPayload['message'] ?? $notification->getMessage(),
            'orderStatus' => $orderPayload['status'] ?? '',
        ]);
    }

    public function broadcastNotification(User $recipient, Notification $notification): void
    {
        $userId = (int) $recipient->getId();
        if ($userId <= 0) {
            return;
        }

        $notificationRow = [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'message' => $notification->getMessage(),
            'isRead' => $notification->isRead(),
            'relatedEntity' => $notification->getRelatedEntity(),
            'relatedId' => $notification->getRelatedId(),
            'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        $fcmTitle = $this->fcmTitleForType($notification->getType());

        $this->postBroadcast([
            'userId' => $userId,
            'event' => 'new_notification',
            'notification' => $notificationRow,
            'message' => $notification->getMessage(),
            'fcmToken' => $recipient->getFcmToken(),
            'fcmTitle' => $fcmTitle,
        ]);
    }

    private function fcmTitleForType(string $type): string
    {
        return match (strtolower($type)) {
            'application_approved' => 'Application approved',
            'application_rejected' => 'Application declined',
            'application_submitted' => 'New application',
            'order_update' => 'Order update',
            'payment_approved' => 'Payment approved',
            'payment_rejected' => 'Payment declined',
            'payment_update' => 'Payment update',
            'listing_update', 'listing_approved' => 'Listing update',
            'contract_update' => 'Contract ready',
            default => 'BinRazali',
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postBroadcast(array $body): void
    {
        $url = rtrim($this->broadcastUrl, '/');
        if ($url === '' || $this->internalSecret === '') {
            return;
        }

        try {
            $this->httpClient->request('POST', $url . '/broadcast', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-WS-Secret' => $this->internalSecret,
                ],
                'json' => $body,
                'timeout' => 3,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Realtime broadcast failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
