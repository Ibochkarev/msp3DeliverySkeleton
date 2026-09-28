<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Contract;

use MiniShop3\Controllers\Delivery\Delivery;
use MiniShop3\Controllers\Delivery\DeliveryProviderInterface;
use MiniShop3\Controllers\Delivery\ShipmentProviderInterface;
use MiniShop3\Controllers\Delivery\ShipmentWebhookEvent;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MiniShop3\Services\Shipment\ShipmentStatus;
use MiniShop3\Services\Shipment\ShipmentWebhookHmac;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Ms3SignatureTest extends TestCase
{
    public function testStubSignaturesMatchExpectedContract(): void
    {
        $getCost = new ReflectionMethod(DeliveryProviderInterface::class, 'getCost');
        self::assertSame(['order', 'delivery', 'cost'], $this->params($getCost));
        self::assertSame('float', (string) $getCost->getReturnType());

        $ctor = (new ReflectionClass(Delivery::class))->getConstructor();
        self::assertNotNull($ctor);
        self::assertSame(['ms3', 'config'], $this->params($ctor));

        $verify = new ReflectionMethod(ShipmentProviderInterface::class, 'verifyWebhook');
        self::assertSame(['rawBody', 'payload', 'headers', 'method'], $this->params($verify));
        $parse = new ReflectionMethod(ShipmentProviderInterface::class, 'parseWebhook');
        self::assertSame(['payload', 'headers'], $this->params($parse));

        $event = (new ReflectionClass(ShipmentWebhookEvent::class))->getConstructor();
        self::assertNotNull($event);
        self::assertSame(
            ['eventType', 'orderId', 'externalId', 'trackingNumber', 'providerEventId', 'carrier', 'payload'],
            $this->params($event)
        );

        self::assertSame('preparing', ShipmentStatus::PREPARING);
        self::assertContains(ShipmentStatus::IN_TRANSIT, ShipmentStatus::all());

        $lifecycle = new ReflectionClass(ShipmentLifecycleService::class);
        foreach (['create', 'findByOrderId', 'setTracking', 'transition', 'applyProviderEvent'] as $method) {
            self::assertTrue($lifecycle->hasMethod($method));
        }
        self::assertTrue((new ReflectionClass(ShipmentWebhookHmac::class))->hasMethod('verify'));
        self::assertTrue((new ReflectionClass(ShipmentWebhookHmac::class))->hasMethod('secretFrom'));
    }

    public function testRealMiniShop3SourcesWhenPresent(): void
    {
        $src = getenv('MS3_SRC');
        if (!is_string($src) || $src === '' || !is_dir($src)) {
            self::markTestSkipped('MS3_SRC is not set');
        }
        $files = [
            'Controllers/Delivery/DeliveryProviderInterface.php' => 'function getCost(msOrder $order, msDelivery $delivery, float $cost): float',
            'Controllers/Delivery/ShipmentProviderInterface.php' => 'function verifyWebhook(string $rawBody, array $payload, array $headers, msDelivery $method): bool',
            'Controllers/Delivery/ShipmentWebhookEvent.php' => 'public readonly string $eventType',
            'Services/Shipment/ShipmentStatus.php' => "public const PREPARING = 'preparing'",
            'Services/Shipment/ShipmentLifecycleService.php' => 'function applyProviderEvent(ShipmentWebhookEvent $event, int $deliveryId, string $provider): array',
            'Services/Shipment/ShipmentWebhookHmac.php' => 'function verify(string $rawBody, string $signature, string $secret): bool',
        ];
        foreach ($files as $relative => $needle) {
            $path = rtrim($src, '/') . '/' . $relative;
            self::assertFileExists($path);
            self::assertStringContainsString($needle, (string) file_get_contents($path));
        }
    }

    /**
     * @return list<string>
     */
    private function params(ReflectionMethod $method): array
    {
        $names = [];
        foreach ($method->getParameters() as $parameter) {
            $names[] = $parameter->getName();
        }

        return $names;
    }
}
