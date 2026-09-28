# MiniShop3 integration

Зафиксировано по исходникам ветки `beta` MiniShop3 **1.14.1-beta2**. Не выдумывайте методы сверх этого списка.

## Delivery

Файлы:

- `Controllers/Delivery/DeliveryProviderInterface.php`
- `Controllers/Delivery/Delivery.php`
- `Services/Delivery/DeliveryService.php`

```php
interface DeliveryProviderInterface
{
    public function getCost(msOrder $order, msDelivery $delivery, float $cost): float;
}

abstract class Delivery implements DeliveryProviderInterface
{
    public function __construct(MiniShop3 $ms3, array $config = []);
    public function getCost(msOrder $order, msDelivery $delivery, float $cost): float;
}
```

`DeliveryService::loadDeliveryController(msDelivery $delivery)`:

- пустой `class` → `DefaultDelivery`
- иначе `new $class($this->ms3, [])`
- объект должен быть `DeliveryProviderInterface`

Стоимость на витрине: `OrderCostCalculator::getDeliveryCost()` → `$msDelivery->loadController()` → `$msDelivery->getCost($draft, $cartCost)`. События `msOnBeforeGetDeliveryCost`, `msOnGetDeliveryCost`.

В менеджере то же через `ManagerOrderCostRecalculator`.

Дефолтная формула: `OrderCostEngine::calculateDefaultDeliveryCost($modx, $delivery, $cartCost, $cartWeight)` — `free_delivery_amount`, `weight_price * weight`, `price` как число или процент.

Отдельного `DeliveryInterface` и DTO `CostResult` нет. Стоимость — `float`.

## Регистрация провайдера

Строка `ms3_deliveries` с полем `class` = FQCN. Autoload пакета должен отработать до `new $class`. События `msOnGetDeliveryProviders` нет. Processor `GetClass` сканирует только встроенную папку MiniShop3.

Колонка `class`: в mysql metaMap `varchar(255)`. Resolver skeleton проверяет длину FQCN.

Поля `msDelivery`: `name`, `description`, `price`, `weight_price`, `distance_price`, `logo`, `position`, `active`, `class`, `properties`, `validation_rules`, `free_delivery_amount`. `distance_price` в дефолтной формуле не участвует.

`DeliveryCatalogService` отдаёт публичный список без `class` и `properties`. DI-ключ: `ms3_delivery_catalog`.

## Shipment interface

`Controllers/Delivery/ShipmentProviderInterface.php`:

```php
public function verifyWebhook(string $rawBody, array $payload, array $headers, msDelivery $method): bool;
public function parseWebhook(array $payload, array $headers): ?ShipmentWebhookEvent;
```

Create / cancel / track в интерфейсе нет.

## Webhook

`Controllers/Api/Web/DeliveryWebhookController.php`

`POST /api/v1/delivery/webhook/{delivery_id}` в `config/routes/web.php`. Token middleware этот префикс не требует.

Поток:

1. `ShipmentLifecycleService::isEnabled()` — иначе 404 (`ms3_shipment_enabled`)
2. Загрузка `msDelivery`
3. Handler обязан быть `ShipmentProviderInterface`
4. Тело — JSON-объект, не список
5. `verifyWebhook` → иначе 401
6. `parseWebhook` → иначе 400
7. `applyProviderEvent($event, $deliveryId, $provider)` где `$provider` = `msDelivery.class`

## Lifecycle

`Services/Shipment/ShipmentLifecycleService.php`, DI `ms3_shipment_lifecycle`.

Публичные методы: `isEnabled`, `create(int $orderId, array $meta = [])`, `findByOrderId`, `publicListForOrder`, `setTracking`, `transition`, `applyProviderEvent`.

Статусы (`ShipmentStatus`): `preparing`, `shipped`, `in_transit`, `delivered`, `cancelled`, `returned`, `failed`.

Разрешённые переходы зашиты в `ALLOWED_TRANSITIONS`. `preparing → preparing` разрешён.

`external_id` и `carrier` пишет `applyProviderEvent` из `ShipmentWebhookEvent`. Таблицы: `ms3_shipments`, `ms3_shipment_events` (PDO, не xPDO). Поля отгрузки: `order_id`, `delivery_id`, `status`, `tracking_number`, `external_id`, `provider`, `carrier`, `shipped_at`, `delivered_at`, `last_event_id`, `meta`.

`ShipmentWebhookEvent`:

```php
public function __construct(
    public readonly string $eventType,
    public readonly ?int $orderId = null,
    public readonly ?string $externalId = null,
    public readonly ?string $trackingNumber = null,
    public readonly ?string $providerEventId = null,
    public readonly ?string $carrier = null,
    public readonly array $payload = [],
);
```

`eventType` должен совпасть с `ShipmentStatus`. `providerEventId` даёт идемпотентность webhook через unique `(shipment_id, provider_event_id)`.

Секреты в `meta` вычищает `BLOCKED_META_KEYS`: `password`, `secret`, `token`, `api_key`, `secret_key`, `properties`, `class`, `authorization`.

HMAC-справка: `ShipmentWebhookHmac::verify($rawBody, $signature, $secret)` и `secretFrom(msDelivery)` (`properties.secret` / `secret_key` / `webhook_secret`).

События MODX: `msOnBeforeCreateShipment`, `msOnCreateShipment`, `msOnBeforeChangeShipmentStatus`, `msOnChangeShipmentStatus`, `msOnBeforeUpdateShipmentTracking`, `msOnUpdateShipmentTracking`.

Синхронизацию статуса заказа делает сам lifecycle (`ms3_status_sent`, `ms3_status_canceled`, `ms3_shipment_on_*_status`). Провайдер заказ не трогает.

## Manager

`GET|PUT /api/mgr/orders/{id}/shipment` — `OrderShipmentController`. Поля UI: status, tracking_number. Ключ вкладки `ms3_shipment` зарезервирован.

Свои роуты: `core/config/ms3.routes.d/manager/*.php`, middleware `AuthMiddleware($modx, 'mgr')`. Сигнатура handler: `function (array $vars, modX $modx): Response`.

Свои вкладки: `window.MS3OrderTabsRegistry.register({ key, title, type: 'vue', component, position, hideOnCreate })` плюс событие `msOnManagerCustomCssJs` со `page === 'order'`.

## Заказ, адрес, товары

`msOrder`: `user_id`, `customer_id`, `token`, `uuid`, `cost`, `cart_cost`, `delivery_cost`, `weight`, `status_id`, `delivery_id`, `payment_id`, `order_comment`, `properties`. Связи: `Address`, `Products`, `Delivery`, `Payment`.

`msOrderAddress`: `first_name`, `last_name`, `phone`, `email`, `country`, `index`, `region`, `city`, `metro`, `street`, `building`, `entrance`, `floor`, `room`, `comment`, `text_address`, `properties`. Поля `apartment` нет.

`msOrderProduct`: `product_id`, `name`, `count`, `price`, `weight`, `cost`, `options`, `properties`.

`msProductData`: есть `weight`, нет `length` / `width` / `height`.

## HTTP в MODX 3

`$modx->services` отдаёт PSR-18 `ClientInterface` (Guzzle) и PSR-17 фабрики. Отдельный HTTP-стек в пакете не нужен.

## Версии пакета MiniShop3

`_build/config.inc.php`: version `1.14.1`, release `beta2`. Requires: PHP `>=8.2.0`, MODX `>=3.0.3`.
