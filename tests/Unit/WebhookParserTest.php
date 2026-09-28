<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use Ibochkarev\Msp3DeliverySkeleton\Api\Signature;
use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use Ibochkarev\Msp3DeliverySkeleton\Webhook\WebhookParser;
use MiniShop3\Services\Shipment\ShipmentStatus;
use MiniShop3\Services\Shipment\ShipmentWebhookHmac;
use PHPUnit\Framework\TestCase;

final class WebhookParserTest extends TestCase
{
    public function testValidWebhook(): void
    {
        $raw = (string) file_get_contents(dirname(__DIR__) . '/Fixtures/webhook-valid.json');
        $payload = json_decode($raw, true);
        self::assertIsArray($payload);
        $parser = $this->parser('whsec');
        self::assertTrue($parser->verify($raw, [
            'x-signature' => hash_hmac('sha256', $raw, 'whsec'),
        ]));
        $event = $parser->parse($payload, []);
        self::assertNotNull($event);
        self::assertSame(ShipmentStatus::IN_TRANSIT, $event->eventType);
        self::assertSame(15, $event->orderId);
        self::assertSame('ext-1', $event->externalId);
        self::assertSame('TRK-1', $event->trackingNumber);
    }

    public function testInvalidSignatureAndUnknownStatus(): void
    {
        $parser = $this->parser('whsec');
        self::assertFalse($parser->verify('{}', ['x-signature' => 'nope']));
        $raw = (string) file_get_contents(dirname(__DIR__) . '/Fixtures/webhook-invalid.json');
        $payload = json_decode($raw, true);
        self::assertIsArray($payload);
        self::assertNull($parser->parse($payload, []));
    }

    public function testHmacHelperMatches(): void
    {
        self::assertTrue(ShipmentWebhookHmac::verify('body', hash_hmac('sha256', 'body', 's'), 's'));
    }

    private function parser(string $secret): WebhookParser
    {
        return new WebhookParser(new Signature(new Settings(null, null, ['secret' => $secret])));
    }
}
