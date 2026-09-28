# msp3DeliverySkeleton

Шаблон дополнения доставки для MODX Revolution 3 и MiniShop3. Вы копируете репозиторий, запускаете `bin/init.php` и получаете пакет вида `msp3Cdek` или `msp3RussianPost`. Дальше вы пишете код конкретного перевозчика в местах с `// PROVIDER:`.

Это не универсальный logistics-framework. MiniShop3 владеет заказом, способом доставки и lifecycle отправления. Пакет отвечает за API перевозчика, расчёт стоимости и маппинг статусов.

## Возможности

- Расчёт стоимости через `getCost()` MiniShop3
- Fallback на стандартную формулу MiniShop3, если API не настроен или ответил ошибкой
- Опциональное создание, отмена и синхронизация отправления
- Опциональный webhook через стандартный роут MiniShop3
- Опциональная вкладка заказа с действиями перевозчика
- HTTP-клиент на PSR-18, настройки, `OrderData`, маскирование секретов

## Архитектура

```text
                 MiniShop3
                     │
            ┌────────┴────────┐
            ↓                 ↓
        Delivery           Shipment
            │                 │
       getCost()          create()
            │              cancel()
            ↓              track()
       Provider API           │
                              ↓
                    ShipmentLifecycleService
                              │
                              ↓
                         MiniShop3
```

Два независимых уровня:

1. **Delivery** — обязательный. Стоимость доставки.
2. **Shipment** — по флагу `--keep=shipment`. Отправление, трек, статусы.

Webhook и manager UI тоже опциональны. Простой перевозчик с фиксированной ценой не тащит shipment.

## Requirements

- PHP 8.2+
- MODX Revolution 3.x (>= 3.0.3)
- MiniShop3 >= 1.14.0-beta1 (ветка `beta`, проверено на 1.14.1-beta2)

## Installation

Склонируйте репозиторий или скопируйте каталог в `Extras/` рядом с сайтом MODX, чтобы `_build/build.php` нашёл `core/config/config.inc.php`.

```bash
git clone git@github.com:Ibochkarev/msp3DeliverySkeleton.git
cd msp3DeliverySkeleton
composer install
```

## Creating a delivery addon

```bash
git clone git@github.com:Ibochkarev/msp3DeliverySkeleton.git
cp -R msp3DeliverySkeleton ../msp3Cdek
cd ../msp3Cdek
php bin/init.php --name=msp3Cdek --provider=Cdek
```

После init в runtime-коде не остаётся `msp3DeliverySkeleton`, `SkeletonDelivery` и `Ibochkarev\Msp3DeliverySkeleton`. Каталог `bin/` удаляется.

## CLI

```bash
php bin/init.php --name=msp3Cdek --provider=Cdek
php bin/init.php --name=msp3Cdek --provider=Cdek --keep=shipment
php bin/init.php --name=msp3Cdek --provider=Cdek --keep=shipment,webhook,manager
php bin/init.php --name=msp3Cdek --provider=Cdek --vendor=Ibochkarev --skip-checks
php bin/init.php --name=msp3Cdek --provider=Cdek --no-encrypt
```

`--name` только в форме `msp3Cdek`, `msp3RussianPost`, `msp3YandexDelivery`. Не пройдут `Cdek`, `ms3Cdek`, `msp3-cdek`.

`--provider` — имя PHP-класса: `Cdek`, `RussianPost`.

`--keep=manager` автоматически включает `shipment`.

`--skip-checks` пропускает `composer test` после генерации.

`--no-encrypt` ставит `$encryptEnabled = false` в `_build/config.inc.php`. Нужен, если extra не регистрируют в [modstore.pro](https://modstore.pro/info/api).

## Example

```php
class CdekDelivery extends Delivery
{
    public function getCost(msOrder $order, msDelivery $delivery, float $cost): float
    {
        $data = OrderData::fromOrder($order);
        $response = $this->api->post('/calculate', [
            'city' => $data->getCity(),
            'weight' => $data->getWeight(),
        ]);

        // PROVIDER: map provider response

        return (float) $response->json()['cost'];
    }
}
```

Реальные методы skeleton смотрите в `SkeletonDelivery::buildCostRequest()` и `mapCostResponse()`.

## Delivery

Класс `SkeletonDelivery` расширяет `MiniShop3\Controllers\Delivery\Delivery` и реализует `DeliveryProviderInterface::getCost(msOrder, msDelivery, float): float`.

MiniShop3 создаёт handler так: `new $class($ms3, [])`. Настройки читайте из `$delivery->get('properties')` или system settings, не из `$config`.

Если `api_url` пустой, вызывается `parent::getCost()` — вес, порог бесплатной доставки, фиксированная или процентная цена. Если API отвечает ошибкой, skeleton пишет безопасный лог и тоже падает в эту формулу, чтобы checkout не ломался.

## Shipment

`ShipmentProviderInterface` в MiniShop3 содержит только `verifyWebhook` и `parseWebhook`. Create / cancel / sync живут в `SkeletonShipment` и пишут состояние через `ShipmentLifecycleService`. Не вызывайте `$order->set('status_id', ...)`.

`external_id` и `carrier` ядро принимает через `applyProviderEvent(ShipmentWebhookEvent, ...)`. Колонки `label_url` в `ms3_shipments` нет. Ссылку на накладную кладите в `meta`.

## Webhook

Стандартный URL MiniShop3:

`POST {site}/assets/components/minishop3/api.php/api/v1/delivery/webhook/{delivery_id}`

Нужен системный флаг `ms3_shipment_enabled`. Свой endpoint skeleton не ставит. Если перевозчик шлёт XML или form-data, см. `docs/PROVIDER-PORTING.md`.

## Settings

Ключи: `api_url`, `api_key`, `account`, `secret`, `test_mode`, `timeout`, `debug`.

Приоритет: `msDelivery.properties`, затем `msp3deliveryskeleton_*`. После init префикс становится именем пакета.

Секреты не попадают в exception, лог, webhook payload, manager JSON и `__debugInfo()`. Формат маски: `sk_l********1234`.

## Manager UI

Вкладка `ms3_shipment` уже есть в MiniShop3. Её не дублируйте. `--keep=manager` добавляет соседнюю вкладку через `MS3OrderTabsRegistry`: provider, external id, tracking, status, label и кнопки Create / Cancel / Sync.

Сборки фронтенда нет. Это статический ES-модуль и CSS на BEM + CSS variables + rem.

## Build

По умолчанию категория пакета шифруется через `EncryptedVehicle` и ключ с [modstore.pro](https://modstore.pro/info/api). Resolver: [`_build/resolvers/resolve.encryption.php`](_build/resolvers/resolve.encryption.php).

Локальная сборка без лицензии:

```bash
ENCRYPT=0 php _build/build.php
```

Для каталога modstore:

```bash
php _build/build.php
```

Пакет появится в `core/packages/` сайта MODX. Команд `pnpm` в этом репозитории нет.

## Testing

```bash
composer install
composer test
composer phpstan
composer lint
```

Контракт против исходников MiniShop3:

```bash
MS3_SRC=../MiniShop3/core/components/minishop3/src composer test
```

Интеграционных запросов к API перевозчиков тесты не делают.

## Provider development

1. `php bin/init.php --name=... --provider=...`
2. Заполните settings
3. Реализуйте authentication в `Signature`
4. Реализуйте расчёт в `*Delivery::buildCostRequest()` / `mapCostResponse()`
5. При необходимости `--keep=shipment` и методы `SkeletonShipment`
6. Маппинг статусов в `StatusMap`
7. Webhook в `WebhookParser`
8. Тесты и `ENCRYPT=0 php _build/build.php`

Полный чеклист: [docs/CHECKLIST.md](docs/CHECKLIST.md). Портирование: [docs/PROVIDER-PORTING.md](docs/PROVIDER-PORTING.md).

## Project structure

```text
msp3DeliverySkeleton/
├── bin/init.php
├── docs/
├── _build/
├── core/components/msp3deliveryskeleton/src/
│   ├── Api/
│   ├── Delivery/SkeletonDelivery.php
│   ├── Shipment/
│   ├── Service/
│   ├── Transport/
│   └── Webhook/
└── assets/components/msp3deliveryskeleton/
```

## Security

Не логируйте `api_key`, `secret`, `authorization`, телефон, email и адрес целиком. `SafeLogger` маскирует эти поля. Debug-трасса включается только настройкой `debug`.

Повторы HTTP skeleton не делает сам. POST создания отправления не ретрайте вслепую. Для GET можно добавить ограниченный retry в точке `// PROVIDER: retry`.

## MiniShop3 integration

Реальные классы и сигнатуры: [docs/MS3-INTEGRATION.md](docs/MS3-INTEGRATION.md). Обзор слоёв: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## License

GPL-2.0-or-later
