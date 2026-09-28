<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Shipment;

use Ibochkarev\Msp3DeliverySkeleton\Api\ApiClient;
use Ibochkarev\Msp3DeliverySkeleton\Api\ApiResponse;
use Ibochkarev\Msp3DeliverySkeleton\Delivery\SkeletonDelivery;
use Ibochkarev\Msp3DeliverySkeleton\Exception\DeliveryException;
use Ibochkarev\Msp3DeliverySkeleton\Service\OrderData;
use Ibochkarev\Msp3DeliverySkeleton\Service\SafeLogger;
use Ibochkarev\Msp3DeliverySkeleton\Service\StatusMap;
use MiniShop3\Controllers\Delivery\ShipmentWebhookEvent;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MiniShop3\Services\Shipment\ShipmentStatus;

final class SkeletonShipment
{
    public function __construct(
        private readonly ApiClient $api,
        private readonly ShipmentLifecycleService $lifecycle,
        private readonly StatusMap $statusMap = new StatusMap(),
        private readonly SafeLogger $logger = new SafeLogger(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(msOrder $order): array
    {
        $orderId = (int) $order->get('id');
        $existing = $this->lifecycle->findByOrderId($orderId);
        if (is_array($existing) && $this->nonEmpty($existing['external_id'] ?? null)) {
            return $existing;
        }

        $data = OrderData::fromOrder($order);
        $headers = [];
        // PROVIDER: idempotency
        $headers['Idempotency-Key'] = 'order-' . $orderId;
        // PROVIDER: create shipment
        $response = $this->api->post('/shipments', $this->buildCreateRequest($data), $headers);
        $mapped = $this->mapCreateResponse($response);

        return $this->lifecycle->applyProviderEvent(
            $this->event(
                ShipmentStatus::PREPARING,
                $order,
                $mapped,
                'create:' . $mapped->externalId
            ),
            (int) $order->get('delivery_id'),
            SkeletonDelivery::class
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(msOrder $order): array
    {
        $row = $this->requireShipment($order);
        $externalId = (string) ($row['external_id'] ?? '');
        if ($externalId !== '') {
            // PROVIDER: cancel shipment
            $this->api->post('/shipments/' . rawurlencode($externalId) . '/cancel');
        }

        return $this->lifecycle->transition((int) $row['id'], ShipmentStatus::CANCELLED);
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(msOrder $order): array
    {
        $row = $this->requireShipment($order);
        $externalId = (string) ($row['external_id'] ?? '');
        if ($externalId === '') {
            throw new DeliveryException('Shipment has no external_id');
        }
        $response = $this->api->get('/shipments/' . rawurlencode($externalId));
        $mapped = $this->mapStatusResponse($response);
        $status = $mapped->status;
        if ($status === null) {
            throw new DeliveryException('Unknown provider status', $response->status(), null, $response->requestId());
        }

        return $this->lifecycle->applyProviderEvent(
            $this->event($status, $order, $mapped, 'sync:' . $status),
            (int) $order->get('delivery_id'),
            SkeletonDelivery::class
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateRequest(OrderData $data): array
    {
        return [
            'order_id' => (int) $data->getOrder()->get('id'),
            'weight' => $data->getWeight(),
            'city' => $data->getCity(),
            'index' => $data->getIndex(),
            'address' => [
                'street' => $data->getStreet(),
                'building' => $data->getBuilding(),
                'room' => $data->getRoom(),
            ],
            'products' => $data->getProducts(),
        ];
    }

    private function mapCreateResponse(ApiResponse $response): ShipmentResponse
    {
        $json = $response->json();
        $externalId = $json['id'] ?? $json['external_id'] ?? null;
        if (!is_scalar($externalId) || (string) $externalId === '') {
            throw new DeliveryException('Provider shipment id missing', $response->status(), null, $response->requestId());
        }

        return new ShipmentResponse(
            (string) $externalId,
            $this->stringOrNull($json['tracking_number'] ?? $json['tracking'] ?? null),
            $this->statusMap->map((string) ($json['status'] ?? ShipmentStatus::PREPARING)),
            $this->safeUrl($json['label_url'] ?? $json['label'] ?? null),
        );
    }

    private function mapStatusResponse(ApiResponse $response): ShipmentResponse
    {
        $json = $response->json();

        return new ShipmentResponse(
            (string) ($json['id'] ?? $json['external_id'] ?? ''),
            $this->stringOrNull($json['tracking_number'] ?? $json['tracking'] ?? null),
            $this->statusMap->map((string) ($json['status'] ?? '')),
            $this->safeUrl($json['label_url'] ?? $json['label'] ?? null),
        );
    }

    private function safeUrl(mixed $value): ?string
    {
        $url = $this->stringOrNull($value);
        if ($url === null) {
            return null;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireShipment(msOrder $order): array
    {
        $row = $this->lifecycle->findByOrderId((int) $order->get('id'));
        if (!is_array($row)) {
            throw new DeliveryException('Shipment not found');
        }

        return $row;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function metaFrom(ShipmentResponse $mapped): array
    {
        $meta = $mapped->meta;
        if ($mapped->labelUrl !== null) {
            $meta['label_url'] = $mapped->labelUrl;
        }

        return $meta;
    }

    private function event(string $status, msOrder $order, ShipmentResponse $mapped, string $eventId): ShipmentWebhookEvent
    {
        $this->logger->debug('Shipment lifecycle event', [
            'event_id' => $eventId,
            'status' => $status,
            'order_id' => (int) $order->get('id'),
        ]);

        return new ShipmentWebhookEvent(
            $status,
            (int) $order->get('id'),
            $mapped->externalId !== '' ? $mapped->externalId : null,
            $mapped->trackingNumber,
            $eventId,
            null,
            $this->metaFrom($mapped),
        );
    }

    private function nonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
