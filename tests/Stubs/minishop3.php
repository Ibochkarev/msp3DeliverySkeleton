<?php

declare(strict_types=1);

namespace MiniShop3 {
    final class MiniShop3
    {
        public \MODX\Revolution\modX $modx;
        public mixed $cart = null;
        public object $services;
        /** @var array<string, mixed> */
        public array $config = [];
        public object $utils;

        public function __construct(\MODX\Revolution\modX $modx)
        {
            $this->modx = $modx;
            $this->services = $modx->services;
            $this->utils = new class () {
                /** @param array<string, mixed> $data */
                public function error(string $message = '', array $data = [], array $placeholders = []): array
                {
                    unset($placeholders);

                    return ['success' => false, 'message' => $message, 'data' => $data];
                }

                /** @param array<string, mixed> $data */
                public function success(string $message = '', array $data = [], array $placeholders = []): array
                {
                    unset($placeholders);

                    return ['success' => true, 'message' => $message, 'data' => $data];
                }
            };
        }
    }
}

namespace MiniShop3\Controllers\Delivery {
    use MiniShop3\MiniShop3;
    use MiniShop3\Model\msDelivery;
    use MiniShop3\Model\msOrder;
    use MiniShop3\Services\Order\OrderCostEngine;
    use MODX\Revolution\modX;

    interface DeliveryProviderInterface
    {
        public function getCost(msOrder $order, msDelivery $delivery, float $cost): float;
    }

    interface ShipmentProviderInterface
    {
        /**
         * @param array<string, mixed> $payload
         * @param array<string, string> $headers
         */
        public function verifyWebhook(string $rawBody, array $payload, array $headers, msDelivery $method): bool;

        /**
         * @param array<string, mixed> $payload
         * @param array<string, string> $headers
         */
        public function parseWebhook(array $payload, array $headers): ?ShipmentWebhookEvent;
    }

    abstract class Delivery implements DeliveryProviderInterface
    {
        protected modX $modx;
        protected MiniShop3 $ms3;
        /** @var array<string, mixed> */
        protected array $config = [];

        /**
         * @param array<string, mixed> $config
         */
        public function __construct(MiniShop3 $ms3, array $config = [])
        {
            $this->ms3 = $ms3;
            $this->modx = $ms3->modx;
            $this->config = $config;
        }

        public function getCost(msOrder $order, msDelivery $delivery, float $cost): float
        {
            return OrderCostEngine::calculateDefaultDeliveryCost(
                $this->modx,
                $delivery,
                $cost,
                (float) $order->get('weight')
            );
        }
    }

    final class ShipmentWebhookEvent
    {
        /**
         * @param array<string, mixed> $payload
         */
        public function __construct(
            public readonly string $eventType,
            public readonly ?int $orderId = null,
            public readonly ?string $externalId = null,
            public readonly ?string $trackingNumber = null,
            public readonly ?string $providerEventId = null,
            public readonly ?string $carrier = null,
            public readonly array $payload = [],
        ) {
        }
    }
}

namespace MiniShop3\Model {
    abstract class SimpleRecord
    {
        /** @param array<string, mixed> $fields */
        public function __construct(
            protected array $fields = [],
            /** @var array<string, object|list<object>|null> */
            protected array $related = [],
        ) {
        }

        public function get(string $key): mixed
        {
            return $this->fields[$key] ?? null;
        }

        public function set(string $key, mixed $value): void
        {
            $this->fields[$key] = $value;
        }

        public function getOne(string $alias): ?object
        {
            $value = $this->related[$alias] ?? null;

            return is_object($value) ? $value : null;
        }

        /**
         * @return list<object>
         */
        public function getMany(string $alias): array
        {
            $value = $this->related[$alias] ?? [];

            return is_array($value) ? $value : [];
        }
    }

    final class msOrder extends SimpleRecord
    {
    }

    final class msDelivery extends SimpleRecord
    {
    }

    final class msOrderAddress extends SimpleRecord
    {
    }

    final class msOrderProduct extends SimpleRecord
    {
    }
}

namespace MiniShop3\Services\Order {
    use MiniShop3\Model\msDelivery;
    use MODX\Revolution\modX;

    final class OrderCostEngine
    {
        public static function calculateDefaultDeliveryCost(
            modX $modx,
            msDelivery $delivery,
            float $cartCost,
            float $orderWeight
        ): float {
            unset($modx);
            $free = (float) $delivery->get('free_delivery_amount');
            if ($free > 0 && $cartCost >= $free) {
                return 0.0;
            }
            $price = (float) $delivery->get('price');
            $weightPrice = (float) $delivery->get('weight_price');

            return round($price + $weightPrice * $orderWeight, 6);
        }
    }
}

namespace MiniShop3\Services\Shipment {
    use MiniShop3\Controllers\Delivery\ShipmentWebhookEvent;
    use MiniShop3\Model\msDelivery;
    use MODX\Revolution\modX;

    final class ShipmentStatus
    {
        public const PREPARING = 'preparing';
        public const SHIPPED = 'shipped';
        public const IN_TRANSIT = 'in_transit';
        public const DELIVERED = 'delivered';
        public const CANCELLED = 'cancelled';
        public const RETURNED = 'returned';
        public const FAILED = 'failed';

        /**
         * @return list<string>
         */
        public static function all(): array
        {
            return [
                self::PREPARING,
                self::SHIPPED,
                self::IN_TRANSIT,
                self::DELIVERED,
                self::CANCELLED,
                self::RETURNED,
                self::FAILED,
            ];
        }
    }

    final class ShipmentWebhookHmac
    {
        public static function verify(string $rawBody, string $signature, string $secret): bool
        {
            if ($rawBody === '' || $signature === '' || $secret === '') {
                return false;
            }

            return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
        }

        public static function secretFrom(msDelivery $method): string
        {
            $properties = $method->get('properties');
            if (!is_array($properties)) {
                return '';
            }
            foreach (['secret', 'secret_key', 'webhook_secret'] as $key) {
                $value = $properties[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }

            return '';
        }
    }

    final class ShipmentPublicDto
    {
        /**
         * @param array<string, mixed> $row
         * @return array<string, mixed>
         */
        public static function fromRow(array $row): array
        {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'order_id' => (int) ($row['order_id'] ?? 0),
                'delivery_id' => (int) ($row['delivery_id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'tracking_number' => $row['tracking_number'] ?? null,
                'carrier' => $row['carrier'] ?? null,
                'shipped_at' => $row['shipped_at'] ?? null,
                'delivered_at' => $row['delivered_at'] ?? null,
            ];
        }
    }

    final class ShipmentLifecycleService
    {
        /** @var array<int, array<string, mixed>> */
        private array $rows = [];
        private int $seq = 1;

        public function __construct()
        {
        }

        public static function isEnabled(modX $modx): bool
        {
            unset($modx);

            return true;
        }

        /**
         * @param array<string, mixed> $meta
         * @return array<string, mixed>
         */
        public function create(int $orderId, array $meta = []): array
        {
            $existing = $this->findByOrderId($orderId);
            if ($existing !== null) {
                return $existing;
            }
            $row = [
                'id' => $this->seq++,
                'order_id' => $orderId,
                'delivery_id' => 1,
                'status' => ShipmentStatus::PREPARING,
                'tracking_number' => null,
                'external_id' => null,
                'provider' => null,
                'carrier' => null,
                'meta' => $meta,
            ];
            $this->rows[$row['id']] = $row;

            return $row;
        }

        /**
         * @return array<string, mixed>|null
         */
        public function findByOrderId(int $orderId): ?array
        {
            foreach ($this->rows as $row) {
                if ((int) $row['order_id'] === $orderId) {
                    return $row;
                }
            }

            return null;
        }

        /**
         * @return array<string, mixed>
         */
        public function setTracking(int $shipmentId, string $trackingNumber, ?string $eventId = null): array
        {
            unset($eventId);
            $this->rows[$shipmentId]['tracking_number'] = $trackingNumber;

            return $this->rows[$shipmentId];
        }

        /**
         * @return array<string, mixed>
         */
        public function transition(int $shipmentId, string $target, ?string $eventId = null): array
        {
            unset($eventId);
            $this->rows[$shipmentId]['status'] = $target;

            return $this->rows[$shipmentId];
        }

        /**
         * @return array<string, mixed>
         */
        public function applyProviderEvent(ShipmentWebhookEvent $event, int $deliveryId, string $provider): array
        {
            unset($provider);
            $row = $event->orderId !== null ? $this->findByOrderId($event->orderId) : null;
            if ($row === null) {
                $row = $this->create($event->orderId ?? 0);
            }
            $row['delivery_id'] = $deliveryId;
            $row['status'] = $event->eventType;
            if ($event->externalId !== null) {
                $row['external_id'] = $event->externalId;
            }
            if ($event->trackingNumber !== null) {
                $row['tracking_number'] = $event->trackingNumber;
            }
            if ($event->payload !== []) {
                $row['meta'] = array_merge($row['meta'] ?? [], $event->payload);
            }
            $this->rows[(int) $row['id']] = $row;

            return $row;
        }
    }
}

namespace MiniShop3\Router {
    final class Response
    {
        /**
         * @param array<string, mixed> $payload
         */
        public function __construct(private readonly array $payload, private readonly int $status = 200)
        {
        }

        public static function success(mixed $data = null, ?string $message = null, int $statusCode = 200): self
        {
            return new self(['success' => true, 'message' => $message, 'data' => $data], $statusCode);
        }

        public static function error(string $message, int $statusCode = 400, mixed $errors = null, ?string $errorCode = null, mixed $data = null): self
        {
            return new self([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'error_code' => $errorCode,
                'data' => $data,
            ], $statusCode);
        }

        /**
         * @return array<string, mixed>
         */
        public function getData(): array
        {
            return $this->payload;
        }

        public function getStatusCode(): int
        {
            return $this->status;
        }
    }
}

namespace MODX\Revolution {
    final class ServiceBag
    {
        /** @var array<string, object> */
        public array $items = [];

        public function has(string $id): bool
        {
            return isset($this->items[$id]);
        }

        public function get(string $id): mixed
        {
            return $this->items[$id] ?? null;
        }

        public function add(string $id, object $service): void
        {
            $this->items[$id] = $service;
        }
    }

    class modX
    {
        public const LOG_LEVEL_ERROR = 0;
        public const LOG_LEVEL_INFO = 1;
        public const LOG_LEVEL_DEBUG = 3;

        public ServiceBag $services;
        /** @var list<string> */
        public array $logs = [];
        /** @var array<string, object> */
        public array $objects = [];
        /** @var array<string, bool> */
        public array $permissions = ['msorder_save' => true, 'msorder_view' => true, 'msorder_list' => true];
        /** @var array<string, mixed> */
        public array $options = [];

        public function __construct()
        {
            $this->services = new ServiceBag();
        }

        public function getOption(string $key, mixed $options = null, mixed $default = null): mixed
        {
            unset($options);

            return $this->options[$key] ?? $default;
        }

        public function log(int $level, string $message): void
        {
            $this->logs[] = $level . ':' . $message;
        }

        public function getObject(string $class, mixed $criteria): mixed
        {
            $id = is_array($criteria) ? ($criteria['id'] ?? null) : $criteria;

            return $this->objects[$class . ':' . $id] ?? null;
        }

        public function hasPermission(string $permission): bool
        {
            return $this->permissions[$permission] ?? false;
        }
    }
}
