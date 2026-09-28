<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Manager;

use Ibochkarev\Msp3DeliverySkeleton\Api\ApiClient;
use Ibochkarev\Msp3DeliverySkeleton\Api\Signature;
use Ibochkarev\Msp3DeliverySkeleton\Exception\DeliveryException;
use Ibochkarev\Msp3DeliverySkeleton\Service\SafeLogger;
use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use Ibochkarev\Msp3DeliverySkeleton\Service\StatusMap;
use Ibochkarev\Msp3DeliverySkeleton\Shipment\SkeletonShipment;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Router\Response;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MiniShop3\Services\Shipment\ShipmentPublicDto;
use MODX\Revolution\modX;
use Throwable;

final class ShipmentActions
{
    /**
     * @param array<string, mixed> $vars
     */
    public static function get(array $vars, modX $modx): Response
    {
        return self::run($vars, $modx, 'view', static function (SkeletonShipment $shipment, msOrder $order, ShipmentLifecycleService $lifecycle): array {
            unset($shipment);

            return $lifecycle->findByOrderId((int) $order->get('id')) ?? [];
        });
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function create(array $vars, modX $modx): Response
    {
        return self::run($vars, $modx, 'save', static fn (SkeletonShipment $shipment, msOrder $order): array => $shipment->create($order));
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function cancel(array $vars, modX $modx): Response
    {
        return self::run($vars, $modx, 'save', static fn (SkeletonShipment $shipment, msOrder $order): array => $shipment->cancel($order));
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function sync(array $vars, modX $modx): Response
    {
        return self::run($vars, $modx, 'save', static fn (SkeletonShipment $shipment, msOrder $order): array => $shipment->sync($order));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function toPublic(array $row): array
    {
        if ($row === []) {
            return [];
        }
        $public = class_exists(ShipmentPublicDto::class)
            ? ShipmentPublicDto::fromRow($row)
            : [
                'id' => (int) ($row['id'] ?? 0),
                'order_id' => (int) ($row['order_id'] ?? 0),
                'delivery_id' => (int) ($row['delivery_id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'tracking_number' => $row['tracking_number'] ?? null,
                'carrier' => $row['carrier'] ?? null,
            ];
        $public['external_id'] = $row['external_id'] ?? null;
        $meta = $row['meta'] ?? [];
        $public['label_url'] = is_array($meta) ? ($meta['label_url'] ?? null) : null;

        return $public;
    }

    /**
     * @param array<string, mixed> $vars
     * @param callable(SkeletonShipment, msOrder, ShipmentLifecycleService): array<string, mixed> $action
     */
    private static function run(array $vars, modX $modx, string $permission, callable $action): Response
    {
        $needed = $permission === 'save' ? ['msorder_save'] : ['msorder_view', 'msorder_list'];
        if (!self::allowed($modx, $needed)) {
            return Response::error('Access denied', 403);
        }
        $orderId = (int) ($vars['id'] ?? 0);
        $order = $modx->getObject(msOrder::class, $orderId);
        if (!$order instanceof msOrder) {
            return Response::error('Order not found', 404);
        }
        $delivery = $modx->getObject(msDelivery::class, (int) $order->get('delivery_id'));
        if (!$delivery instanceof msDelivery) {
            return Response::error('Delivery method not found', 400);
        }
        $class = (string) $delivery->get('class');
        if (!str_starts_with($class, 'Ibochkarev\\Msp3DeliverySkeleton\\')) {
            return Response::error('Delivery method is not this provider', 400);
        }
        if (!$modx->services->has('ms3_shipment_lifecycle')) {
            return Response::error('Shipment lifecycle is unavailable', 500);
        }

        try {
            $lifecycle = $modx->services->get('ms3_shipment_lifecycle');
            if (!$lifecycle instanceof ShipmentLifecycleService) {
                return Response::error('Shipment lifecycle is unavailable', 500);
            }
            $settings = Settings::fromDelivery($delivery, $modx);
            $logger = new SafeLogger($modx, $settings->debug());
            $api = ApiClient::fromModx($modx, $settings, new Signature($settings), $logger);
            $shipment = new SkeletonShipment($api, $lifecycle, new StatusMap(), $logger);
            $row = $action($shipment, $order, $lifecycle);

            return Response::success(self::toPublic($row));
        } catch (DeliveryException $exception) {
            return Response::error($exception->getMessage(), $exception->httpStatus ?? 400);
        } catch (Throwable $exception) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[msp3DeliverySkeleton] Manager action failed');

            return Response::error('Provider request failed', 500);
        }
    }

    /**
     * @param list<string> $permissions
     */
    private static function allowed(modX $modx, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($modx->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
