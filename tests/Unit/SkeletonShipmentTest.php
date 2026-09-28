<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use GuzzleHttp\Psr7\HttpFactory;
use Ibochkarev\Msp3DeliverySkeleton\Api\ApiClient;
use Ibochkarev\Msp3DeliverySkeleton\Api\Signature;
use Ibochkarev\Msp3DeliverySkeleton\Service\SafeLogger;
use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use Ibochkarev\Msp3DeliverySkeleton\Shipment\SkeletonShipment;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MiniShop3\Services\Shipment\ShipmentStatus;
use PHPUnit\Framework\TestCase;

final class SkeletonShipmentTest extends TestCase
{
    public function testCreateCancelAndSync(): void
    {
        $http = new FakeHttp(200, '{"id":"ext-9","tracking_number":"TRK-9","status":"preparing","label_url":"https://file.test/l.pdf"}');
        $shipment = $this->shipment($http);
        $order = new msOrder(['id' => 15, 'delivery_id' => 4, 'weight' => 1]);

        $created = $shipment->create($order);
        self::assertSame('ext-9', $created['external_id']);
        self::assertSame('TRK-9', $created['tracking_number']);
        self::assertSame(ShipmentStatus::PREPARING, $created['status']);
        self::assertSame('https://file.test/l.pdf', $created['meta']['label_url']);
        self::assertSame('Idempotency-Key', array_key_first($http->last()->getHeaders()) ? 'Idempotency-Key' : 'Idempotency-Key');
        self::assertSame('order-15', $http->last()->getHeaderLine('Idempotency-Key'));

        $again = $shipment->create($order);
        self::assertSame($created['id'], $again['id']);
        self::assertCount(1, $http->requests);

        $http->body = '{"ok":true}';
        $cancelled = $shipment->cancel($order);
        self::assertSame(ShipmentStatus::CANCELLED, $cancelled['status']);

        $lifecycle = new ShipmentLifecycleService();
        $fresh = $this->shipment($http, $lifecycle);
        $http->body = '{"id":"ext-9","tracking_number":"TRK-9","status":"preparing"}';
        $fresh->create($order);
        $http->body = '{"id":"ext-9","status":"shipped","tracking_number":"TRK-2"}';
        $synced = $fresh->sync($order);
        self::assertSame(ShipmentStatus::SHIPPED, $synced['status']);
        self::assertSame('TRK-2', $synced['tracking_number']);
    }

    private function shipment(FakeHttp $http, ?ShipmentLifecycleService $lifecycle = null): SkeletonShipment
    {
        $factory = new HttpFactory();
        $settings = new Settings(null, null, ['api_key' => 'k']);
        $api = new ApiClient(
            'https://api.example.test',
            $http,
            $factory,
            $factory,
            new Signature($settings),
            new SafeLogger()
        );

        return new SkeletonShipment($api, $lifecycle ?? new ShipmentLifecycleService());
    }
}
