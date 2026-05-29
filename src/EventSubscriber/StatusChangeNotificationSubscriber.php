<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\Product;
use App\Service\NotificationService;
use App\Service\OrderStatusLabelService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Creates notifications and order_updated broadcasts when admin/staff updates record status.
 * Work is deferred to postFlush so we never nest flush() inside preUpdate.
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class StatusChangeNotificationSubscriber
{
    /** @var list<array{kind: string, payload: array<string, mixed>}> */
    private array $pending = [];

    public function __construct(private NotificationService $notifications)
    {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Application && $args->hasChangedField('status')) {
            $this->pending[] = [
                'kind' => 'application',
                'application' => $entity,
                'old' => (string) $args->getOldValue('status'),
                'new' => (string) $args->getNewValue('status'),
            ];

            return;
        }

        if ($entity instanceof Payment && $args->hasChangedField('status')) {
            $this->pending[] = [
                'kind' => 'payment',
                'payment' => $entity,
                'new' => (string) $args->getNewValue('status'),
            ];

            return;
        }

        if ($entity instanceof Product && $args->hasChangedField('status')) {
            $this->pending[] = [
                'kind' => 'product',
                'product' => $entity,
                'new' => (string) $args->getNewValue('status'),
            ];
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pending === []) {
            return;
        }

        $queue = $this->pending;
        $this->pending = [];

        foreach ($queue as $item) {
            match ($item['kind']) {
                'application' => $this->dispatchApplication($item),
                'payment' => $this->dispatchPayment($item),
                'product' => $this->dispatchProduct($item),
                default => null,
            };
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dispatchApplication(array $item): void
    {
        /** @var Application $application */
        $application = $item['application'];
        $this->notifications->notifyOrderStatusChange(
            $application,
            (string) $item['old'],
            (string) $item['new'],
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dispatchPayment(array $item): void
    {
        /** @var Payment $payment */
        $payment = $item['payment'];
        $tenant = $payment->getApplication()?->getTenant();
        if (!$tenant) {
            return;
        }

        $period = $payment->getNotes()
            ?? $payment->getCreatedAt()?->format('F Y')
            ?? 'your account';

        $newStatus = (string) $item['new'];
        $status = strtolower($newStatus);
        $type = 'payment_update';
        $message = match (true) {
            str_contains($status, 'received'), str_contains($status, 'paid') =>
                sprintf('Your rent payment for %s has been received', $period),
            str_contains($status, 'overdue') =>
                sprintf('Your rent payment for %s is overdue', $period),
            str_contains($status, 'waiv') =>
                sprintf('Your rent payment for %s has been waived', $period),
            default =>
                sprintf('Your payment for %s is now %s', $period, OrderStatusLabelService::label($newStatus)),
        };

        $this->notifications->notifyUser($tenant, $type, $message, 'payment', (int) $payment->getId());
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dispatchProduct(array $item): void
    {
        /** @var Product $product */
        $product = $item['product'];
        $landlord = $product->getCreatedBy();
        if (!$landlord) {
            return;
        }

        $address = $product->getName() ?? 'your property';
        $newStatus = (string) $item['new'];
        $label = OrderStatusLabelService::label($newStatus);

        $this->notifications->notifyUser(
            $landlord,
            'listing_update',
            sprintf('Your listed property (%s) is now %s', $address, $label),
            'product',
            (int) $product->getId(),
        );
    }
}
