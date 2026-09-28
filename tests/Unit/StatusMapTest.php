<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use Ibochkarev\Msp3DeliverySkeleton\Service\StatusMap;
use MiniShop3\Services\Shipment\ShipmentStatus;
use PHPUnit\Framework\TestCase;

final class StatusMapTest extends TestCase
{
    public function testMapsKnownAndRejectsUnknown(): void
    {
        $map = new StatusMap();
        self::assertSame(ShipmentStatus::DELIVERED, $map->map('Delivered'));
        self::assertNull($map->map('lost-in-space'));
        self::assertNull($map->map(''));
    }
}
