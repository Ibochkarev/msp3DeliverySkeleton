<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use GuzzleHttp\Psr7\HttpFactory;
use Ibochkarev\Msp3DeliverySkeleton\Api\ApiClient;
use Ibochkarev\Msp3DeliverySkeleton\Api\Signature;
use Ibochkarev\Msp3DeliverySkeleton\Delivery\SkeletonDelivery;
use Ibochkarev\Msp3DeliverySkeleton\Service\SafeLogger;
use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class SkeletonDeliveryTest extends TestCase
{
    public function testFixedCostWithoutApi(): void
    {
        $handler = $this->handler(new FakeHttp(200, '{"cost":999}'));
        $order = new msOrder(['weight' => 2]);
        $delivery = new msDelivery(['price' => 100, 'weight_price' => 10, 'free_delivery_amount' => 0, 'properties' => []]);
        self::assertSame(120.0, $handler->getCost($order, $delivery, 500));
    }

    public function testProviderCost(): void
    {
        $handler = $this->handler(new FakeHttp(200, '{"cost":350}'), ['api_url' => 'https://api.example.test']);
        $order = new msOrder(['weight' => 2, 'cart_cost' => 500]);
        $delivery = new msDelivery(['price' => 100, 'weight_price' => 10, 'properties' => ['api_url' => 'https://api.example.test']]);
        self::assertSame(350.0, $handler->getCost($order, $delivery, 500));
    }

    public function testErrorFallsBackAndDoesNotLeakSecrets(): void
    {
        $logger = new SafeLogger(null, true);
        $handler = $this->handler(
            new FakeHttp(500, '{"error":"boom"}'),
            ['api_url' => 'https://api.example.test', 'api_key' => 'sk_live_abcdefgh1234'],
            $logger
        );
        $order = new msOrder(['weight' => 1]);
        $delivery = new msDelivery([
            'price' => 50,
            'weight_price' => 0,
            'properties' => ['api_url' => 'https://api.example.test', 'api_key' => 'sk_live_abcdefgh1234'],
        ]);
        self::assertSame(50.0, $handler->getCost($order, $delivery, 100));
        $joined = implode("\n", $logger->records);
        self::assertStringNotContainsString('sk_live_abcdefgh1234', $joined);
    }

    /**
     * @param array<string, scalar|null> $overrides
     */
    private function handler(FakeHttp $http, array $overrides = [], ?SafeLogger $logger = null): SkeletonDelivery
    {
        $modx = new modX();
        $ms3 = new MiniShop3($modx);
        $factory = new HttpFactory();
        $settings = new Settings(null, null, $overrides);
        $api = new ApiClient(
            (string) ($overrides['api_url'] ?? 'https://api.example.test'),
            $http,
            $factory,
            $factory,
            new Signature($settings),
            $logger ?? new SafeLogger(),
        );

        return new SkeletonDelivery($ms3, [
            'api' => $api,
            'settings' => $settings,
            'logger' => $logger,
        ]);
    }
}
