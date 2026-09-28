<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use Ibochkarev\Msp3DeliverySkeleton\Manager\ShipmentActions;
use PHPUnit\Framework\TestCase;

final class ShipmentActionsTest extends TestCase
{
    public function testPublicWhitelistAddsExternalIdAndLabel(): void
    {
        $public = ShipmentActions::toPublic([
            'id' => 3,
            'order_id' => 15,
            'delivery_id' => 4,
            'status' => 'preparing',
            'tracking_number' => 'TRK',
            'carrier' => 'x',
            'external_id' => 'ext',
            'meta' => ['label_url' => 'https://file.test/l.pdf', 'api_key' => 'secret'],
            'class' => 'should-not-copy',
        ]);
        self::assertSame('ext', $public['external_id']);
        self::assertSame('https://file.test/l.pdf', $public['label_url']);
        self::assertArrayNotHasKey('class', $public);
        self::assertArrayNotHasKey('api_key', $public);
        self::assertArrayNotHasKey('meta', $public);
    }
}
