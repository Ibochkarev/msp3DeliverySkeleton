<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Delivery;

use Ibochkarev\Msp3DeliverySkeleton\Api\ApiClient;
use Ibochkarev\Msp3DeliverySkeleton\Api\ApiResponse;
use Ibochkarev\Msp3DeliverySkeleton\Api\Signature;
use Ibochkarev\Msp3DeliverySkeleton\Exception\DeliveryException;
use Ibochkarev\Msp3DeliverySkeleton\Service\OrderData;
use Ibochkarev\Msp3DeliverySkeleton\Service\SafeLogger;
use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use MiniShop3\Controllers\Delivery\Delivery;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
// INIT:webhook:begin
use Ibochkarev\Msp3DeliverySkeleton\Webhook\WebhookParser;
use MiniShop3\Controllers\Delivery\ShipmentProviderInterface;
use MiniShop3\Controllers\Delivery\ShipmentWebhookEvent;

// INIT:webhook:end

class SkeletonDelivery extends Delivery
    // INIT:webhook:begin
implements
    ShipmentProviderInterface
    // INIT:webhook:end
{
    private ?ApiClient $api;
    private ?OrderData $orderData;
    private ?SafeLogger $logger;
    private ?Settings $settings;

    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        parent::__construct($ms3, $config);
        $this->api = isset($config['api']) && $config['api'] instanceof ApiClient ? $config['api'] : null;
        $this->orderData = isset($config['orderData']) && $config['orderData'] instanceof OrderData
            ? $config['orderData']
            : null;
        $this->logger = isset($config['logger']) && $config['logger'] instanceof SafeLogger ? $config['logger'] : null;
        $this->settings = isset($config['settings']) && $config['settings'] instanceof Settings
            ? $config['settings']
            : null;
    }

    public function getCost(msOrder $order, msDelivery $delivery, float $cost): float
    {
        $settings = $this->settings ?? Settings::fromDelivery($delivery, $this->modx);
        if (!$settings->isConfigured()) {
            return parent::getCost($order, $delivery, $cost);
        }

        $logger = $this->logger($settings);
        try {
            $data = $this->orderData ?? OrderData::fromOrder($order);
            $request = $this->buildCostRequest($data, $delivery, $cost);
            $response = $this->api($settings)->post('/calculate', $request);

            return $this->mapCostResponse($response);
        } catch (DeliveryException $exception) {
            $logger->error('Cost calculation failed', [
                'http_status' => $exception->httpStatus,
                'provider_code' => $exception->providerCode,
                'request_id' => $exception->requestId,
            ]);

            return parent::getCost($order, $delivery, $cost);
        } catch (\Throwable $exception) {
            unset($exception);
            $logger->error('Cost calculation failed');

            return parent::getCost($order, $delivery, $cost);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCostRequest(OrderData $data, msDelivery $delivery, float $cost): array
    {
        // PROVIDER: calculate delivery cost
        unset($delivery);

        return [
            'weight' => $data->getWeight(),
            'quantity' => $data->getQuantity(),
            'cost' => $cost,
            'cart_cost' => $data->getCartCost(),
            'city' => $data->getCity(),
            'region' => $data->getRegion(),
            'country' => $data->getCountry(),
            'index' => $data->getIndex(),
            'dimensions' => $data->getDimensions(),
        ];
    }

    protected function mapCostResponse(ApiResponse $response): float
    {
        // PROVIDER: map provider response
        $json = $response->json();
        $value = $json['cost'] ?? $json['price'] ?? $json['delivery_sum'] ?? null;
        if (!is_numeric($value)) {
            throw new DeliveryException('Provider cost missing', $response->status(), null, $response->requestId());
        }

        return (float) $value;
    }

    // INIT:webhook:begin
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function verifyWebhook(string $rawBody, array $payload, array $headers, msDelivery $method): bool
    {
        unset($payload);
        $this->settings ??= Settings::fromDelivery($method, $this->modx);

        return $this->webhookParser($this->settings)->verify($rawBody, $headers);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function parseWebhook(array $payload, array $headers): ?ShipmentWebhookEvent
    {
        $settings = $this->settings ?? new Settings($this->modx);

        return $this->webhookParser($settings)->parse($payload, $headers);
    }

    private function webhookParser(Settings $settings): WebhookParser
    {
        return new WebhookParser(new Signature($settings), $this->logger($settings));
    }

    // INIT:webhook:end

    private function api(Settings $settings): ApiClient
    {
        if ($this->api instanceof ApiClient) {
            return $this->api;
        }

        return ApiClient::fromModx($this->modx, $settings, new Signature($settings), $this->logger($settings));
    }

    private function logger(Settings $settings): SafeLogger
    {
        return $this->logger ?? new SafeLogger($this->modx, $settings->debug());
    }
}
