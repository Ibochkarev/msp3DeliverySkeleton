# Как превратить skeleton в службу доставки

Пример ниже использует `msp3Cdek` только как имя пакета. Код CDEK в репозиторий не кладите.

## 1. Init

```bash
cp -R msp3DeliverySkeleton ../msp3Cdek
cd ../msp3Cdek
php bin/init.php --name=msp3Cdek --provider=Cdek --keep=shipment,webhook
```

`--keep=manager` добавляет вкладку заказа и подтягивает shipment.

## 2. Settings

Откройте `Service/Settings.php` и resolver доставки. Добавьте ключи перевозчика в `msDelivery.properties`. Не рассчитывайте, что у всех API один и тот же набор полей.

## 3. Authentication

`Api/Signature.php`, маркер `// PROVIDER: authentication`.

По умолчанию ставится `Authorization: Bearer {api_key}`. Замените на Basic, HMAC, timestamp или свои заголовки. Для webhook — `// PROVIDER: webhook signature`. Можно вызвать `ShipmentWebhookHmac::verify()`.

## 4. Расчёт стоимости

`CdekDelivery::buildCostRequest()` и `mapCostResponse()`, маркеры `// PROVIDER: calculate delivery cost` и `// PROVIDER: map provider response`.

```php
protected function buildCostRequest(OrderData $data, msDelivery $delivery, float $cost): array
{
    return [
        'to_city' => $data->getCity(),
        'weight' => $data->getWeight(),
        'packages' => $data->getProducts(),
    ];
}

protected function mapCostResponse(ApiResponse $response): float
{
    return (float) $response->json()['delivery_sum'];
}
```

Адрес, вес и товары берите из `OrderData`. Не обходите заказ в поисках `msOrderAddress` по всему пакету.

Габариты: `getDimensions()` читает `options.length/width/height`. Если у перевозчика свои поля, поменяйте этот метод (`// PROVIDER: dimensions`).

## 5. Shipment

Если отправление не нужно, не передавайте `--keep=shipment`. Delivery продолжит работать.

Если нужно:

1. `// PROVIDER: create shipment` в `CdekShipment::create()`
2. `// PROVIDER: idempotency` — заголовок `Idempotency-Key: order-{id}` или аналог API
3. `// PROVIDER: cancel shipment`

Повторный `create()` при уже известном `external_id` не бьёт в API. POST create skeleton не повторяет сам.

Ответ API оставьте внутри `ShipmentResponse`. В MiniShop3 уходит `external_id`, tracking, status, `meta.label_url`.

## 6. Tracking и sync

`CdekShipment::sync()` читает статус, гоняет его через `StatusMap` и вызывает `applyProviderEvent` с `providerEventId = sync:{status}`.

## 7. Webhook

Стандартный URL MiniShop3:

```text
POST /assets/components/minishop3/api.php/api/v1/delivery/webhook/{delivery_id}
```

Включите `ms3_shipment_enabled`. Реализуйте `// PROVIDER: parse webhook`. Неизвестный статус верните как `null` — контроллер ответит 400.

Свой URL нужен, только если перевозчик шлёт не JSON-объект. Поставьте скрипт в `assets/`, разберите XML или form-data, соберите тот же массив и вызовите `WebhookParser` + `ms3_shipment_lifecycle->applyProviderEvent()`. Не копируйте `DeliveryWebhookController`.

## 8. Status mapping

`Service/StatusMap.php`, маркер `// PROVIDER: map provider status`.

Верните константу `ShipmentStatus` или `null`. Статусы конкретных перевозчиков в skeleton не хардкодьте.

## 9. Manager UI

`--keep=manager` даёт вкладку и роуты create/cancel/sync. Не редактируйте status и tracking на этой вкладке: это делает `ms3_shipment`.

## 10. Тесты

Добавьте фикстуры ответов API. Живые запросы к кабинету перевозчика в PHPUnit не вызывайте.

```bash
composer test
composer phpstan
composer lint
```

## 11. Build

```bash
ENCRYPT=0 php _build/build.php
```

Установите transport в MODX, активируйте способ доставки, проверьте checkout.

## Retry

Автоматических бесконечных повторов нет. Если API нестабилен, ограничьте GET двумя-тремя попытками и только на 429/502/503. Таймаут берите из Settings. Создание отправления (POST) не ретрайте: получите дубль на стороне перевозчика.

## Идемпотентность

Точка `// PROVIDER: idempotency`. Skeleton не навязывает конкретный заголовок. Если API его не поддерживает, удалите строку и оставьте проверку `external_id` в `create()`.
