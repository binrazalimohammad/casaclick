<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Maps internal application/order status codes to customer-facing labels.
 */
final class OrderStatusLabelService
{
  /** @var array<string, string> */
    public const LABELS = [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'approved' => 'Accepted',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        'refunded' => 'Refund',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
    ];

    public static function label(string $status): string
    {
        $key = strtolower(trim($status));

        return self::LABELS[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    public static function orderUpdateMessage(string $listingName, string $newStatus): string
    {
        $label = self::label($newStatus);

        return sprintf('Your order for %s is now: %s', $listingName, $label);
    }
}
