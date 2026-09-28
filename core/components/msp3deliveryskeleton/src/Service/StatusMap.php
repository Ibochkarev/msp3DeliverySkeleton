<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Service;

use MiniShop3\Services\Shipment\ShipmentStatus;

final class StatusMap
{
    public function map(string $providerStatus): ?string
    {
        // PROVIDER: map provider status to MiniShop3 status
        $normalized = strtolower(trim($providerStatus));
        if ($normalized === '') {
            return null;
        }
        foreach (ShipmentStatus::all() as $status) {
            if ($status === $normalized) {
                return $status;
            }
        }

        return null;
    }
}
