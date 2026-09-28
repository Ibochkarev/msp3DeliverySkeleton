<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Shipment;

final class ShipmentResponse
{
    /**
     * @param array<string, scalar|null> $meta
     */
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $status = null,
        public readonly ?string $labelUrl = null,
        public readonly array $meta = [],
    ) {
    }
}
