# Архитектура

MiniShop3 хранит заказ, способ доставки и отправление. Пакет ходит во внешний API и возвращает числа и события, которые ядро уже умеет принимать.

## Слои

```text
MiniShop3
├── order
├── delivery          ms3_deliveries.class = FQCN
├── shipment          ms3_shipments + ms3_shipment_events
└── lifecycle         ShipmentLifecycleService

Provider
├── authentication    Signature
├── API requests      ApiClient
├── cost              SkeletonDelivery::getCost
├── external IDs      ShipmentResponse
├── statuses          StatusMap
└── webhook parse     WebhookParser
```

## Delivery

`DeliveryService::loadDeliveryController()` делает `new $class($ms3, [])`. `$config` в проде пустой. В тестах туда можно передать `api`, `settings`, `logger`, `orderData`.

`getCost()`:

1. Читает `Settings` из `msDelivery.properties`
2. Пустой `api_url` → `parent::getCost()` (формула MiniShop3)
3. Иначе `OrderData` → `buildCostRequest()` → `ApiClient::post()` → `mapCostResponse()`
4. `DeliveryException` → безопасный лог → снова `parent::getCost()`

В класс доставки не кладите webhook-парсер огромного размера, manager UI и очередь задач.

## Shipment

`ShipmentProviderInterface` не содержит create/cancel/track. Эти действия — методы `SkeletonShipment`. Они вызывают API, затем:

- `create($orderId, $meta)`
- `applyProviderEvent($event, $deliveryId, $provider)`
- `transition($id, cancelled)`

`eventType` должен быть константой `ShipmentStatus`. Переход `preparing → preparing` ядро разрешает. Так записывается `external_id` сразу после создания.

## Webhook

```text
Provider
    ↓
POST /api/v1/delivery/webhook/{delivery_id}
    ↓
DeliveryWebhookController
    ↓
verifyWebhook / parseWebhook
    ↓
ShipmentWebhookEvent
    ↓
ShipmentLifecycleService::applyProviderEvent
```

Провайдер делает authentication, validation, parse и map status. Lifecycle и статус заказа трогает MiniShop3.

## API

`ApiClient` — тонкая обёртка PSR-18: GET, POST, PUT, DELETE, JSON, query, заголовки. Ответ не 2xx становится `DeliveryException` с HTTP status, кодом провайдера и request id. Тело ответа и секреты в сообщение не попадают.

Guzzle берётся из ядра MODX 3. Свой composer vendor в transport не кладётся.

## Settings и OrderData

`Settings` — pattern, не универсальная схема всех перевозчиков. Добавляйте свои ключи в том же классе.

`OrderData` собирает адрес, товары, вес, суммы и комментарий из `msOrder`, `Address`, `Products`. Квартира в MiniShop3 называется `room`. Габаритов в схеме товара нет: `getDimensions()` читает `options` или возвращает `null`.

## Manager

Фрагмент `core/config/ms3.routes.d/manager/50-*.php` подключает `routes.manager.php`. Handler проверяет mgr-сессию (`AuthMiddleware`) и права `msorder_save` / `msorder_view`. Ответ — whitelist `ShipmentPublicDto` плюс `external_id` и `label_url`.

Вкладка ядра `ms3_shipment` остаётся как есть.

## Build

`_build/build.php` собирает category, plugin `OnMODXInit`, system settings, file vehicles и resolvers. Resolver доставки создаёт неактивный `msDelivery` с FQCN handler. Если FQCN длиннее колонки `class`, resolver пишет ошибку в лог и строку не создаёт.
