<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use MiniShop3\Model\msDelivery;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    public function testPropertiesBeatOverridesAndMaskSecrets(): void
    {
        $delivery = new msDelivery([
            'properties' => [
                'api_url' => 'https://api.example.test',
                'api_key' => 'sk_live_abcdefgh1234',
                'secret' => 'supersecret',
                'test_mode' => false,
            ],
        ]);
        $settings = Settings::fromDelivery($delivery);
        self::assertTrue($settings->isConfigured());
        self::assertSame('https://api.example.test', $settings->apiUrl());
        self::assertSame('sk_live_abcdefgh1234', $settings->apiKey());
        self::assertFalse($settings->testMode());
        $safe = $settings->toSafeArray();
        self::assertSame('********1234', $safe['api_key']);
        self::assertStringNotContainsString('supersecret', (string) json_encode($safe));
        self::assertSame($safe, $settings->__debugInfo());
    }

    public function testOverridesAndEmptyUrl(): void
    {
        $settings = new Settings(null, null, ['api_url' => '', 'timeout' => '0']);
        self::assertFalse($settings->isConfigured());
        self::assertSame(10, $settings->timeout());
        self::assertSame('********1234', Settings::maskSecret('sk_live_abcdefgh1234'));
        self::assertSame('********', Settings::maskSecret('shortkey'));
    }

    public function testEmptyPropertyFallsBackToSystemSetting(): void
    {
        $modx = new \MODX\Revolution\modX();
        $modx->options['msp3deliveryskeleton_api_url'] = 'https://from-setting.test';
        $delivery = new msDelivery(['properties' => ['api_url' => '']]);
        $settings = Settings::fromDelivery($delivery, $modx);
        self::assertSame('https://from-setting.test', $settings->apiUrl());
        self::assertTrue($settings->isConfigured());
    }
}
