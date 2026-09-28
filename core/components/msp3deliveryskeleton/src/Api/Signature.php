<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Api;

use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use MiniShop3\Services\Shipment\ShipmentWebhookHmac;

/**
 * PROVIDER: authentication. Replace Bearer with API key, Basic, HMAC or custom headers.
 */
final class Signature
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function apply(array $headers, string $method, string $path, string $body): array
    {
        // PROVIDER: authentication
        unset($method, $path, $body);
        $apiKey = $this->settings->apiKey();
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }
        $account = $this->settings->account();
        if ($account !== '') {
            $headers['X-Account'] = $account;
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        // PROVIDER: webhook signature
        $signature = $headers['x-signature'] ?? $headers['x-hub-signature'] ?? '';
        $secret = $this->settings->secret();
        if ($secret === '' || $signature === '') {
            return false;
        }

        return ShipmentWebhookHmac::verify($rawBody, $signature, $secret);
    }
}
