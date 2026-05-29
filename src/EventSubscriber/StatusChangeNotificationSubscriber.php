<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Product;
use App\Service\NotificationService;
use App\Service\OrderStatusLabelService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Notifies landlords when listing (product) status changes. Tenant application/payment alerts
 * are sent directly from controllers (see ApplicationController, PaymentController).
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

        // Application/payment tenant alerts are sent explicitly from controllers
        // (same pattern as listing_unoccupied) so they work even if listeners are skipped.

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
            if ($item['kind'] === 'product') {
                $this->dispatchProduct($item);
            }
        }
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
