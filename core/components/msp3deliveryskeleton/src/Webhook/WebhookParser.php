<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Webhook;

use Ibochkarev\Msp3DeliverySkeleton\Api\Signature;
use Ibochkarev\Msp3DeliverySkeleton\Service\SafeLogger;
use Ibochkarev\Msp3DeliverySkeleton\Service\StatusMap;
use MiniShop3\Controllers\Delivery\ShipmentWebhookEvent;

final class WebhookParser
{
    public function __construct(
        private readonly Signature $signature,
        private readonly SafeLogger $logger = new SafeLogger(),
        private readonly StatusMap $statusMap = new StatusMap(),
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function verify(string $rawBody, array $headers): bool
    {
        return $this->signature->verifyWebhook($rawBody, $headers);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function parse(array $payload, array $headers): ?ShipmentWebhookEvent
    {
        unset($headers);
        // PROVIDER: parse webhook
        $status = $this->statusMap->map((string) ($payload['status'] ?? $payload['event'] ?? ''));
        if ($status === null) {
            $this->logger->debug('Webhook status not mapped', [
                'status' => (string) ($payload['status'] ?? ''),
            ]);

            return null;
        }

        $orderId = $payload['order_id'] ?? $payload['orderId'] ?? null;
        $externalId = $payload['external_id'] ?? $payload['id'] ?? null;
        $tracking = $payload['tracking_number'] ?? $payload['tracking'] ?? null;
        $eventId = $payload['event_id'] ?? $payload['eventId'] ?? null;
        $carrier = $payload['carrier'] ?? null;

        return new ShipmentWebhookEvent(
            $status,
            is_numeric($orderId) ? (int) $orderId : null,
            is_scalar($externalId) && (string) $externalId !== '' ? (string) $externalId : null,
            is_scalar($tracking) && (string) $tracking !== '' ? (string) $tracking : null,
            is_scalar($eventId) && (string) $eventId !== '' ? (string) $eventId : null,
            is_scalar($carrier) && (string) $carrier !== '' ? (string) $carrier : null,
            $this->safePayload($payload),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, scalar|null>
     */
    private function safePayload(array $payload): array
    {
        $allowed = ['label_url', 'carrier'];
        $clean = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $payload) || !is_scalar($payload[$key])) {
                continue;
            }
            $value = (string) $payload[$key];
            if ($key === 'label_url') {
                $scheme = parse_url($value, PHP_URL_SCHEME);
                if (!in_array($scheme, ['http', 'https'], true)) {
                    continue;
                }
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
