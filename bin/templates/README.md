# {{NAME}}

MiniShop3 delivery extra generated from msp3DeliverySkeleton for **{{PROVIDER}}**.

Namespace: `{{VENDOR}}\{{NAMESPACE}}`

Enabled modules: {{KEEP}}

## Requirements

- PHP 8.2+
- MODX Revolution 3.x
- MiniShop3 >= 1.14.0-beta1

## Settings

Set `api_url`, `api_key`, `account`, `secret`, `test_mode`, `timeout` on the delivery method properties or as `{{NAME_LOWER}}_*` system settings.

Search the code for `// PROVIDER:` and implement the carrier API.

## Build

```bash
php _build/build.php
```

The transport package appears in `core/packages/`.

## Testing

```bash
composer install
composer test
composer phpstan
composer lint
```

## Webhook

MiniShop3 endpoint:

`POST /assets/components/minishop3/api.php/api/v1/delivery/webhook/{delivery_id}`

Requires `ms3_shipment_enabled` and `ShipmentProviderInterface` on the delivery class.

## License

GPL-2.0-or-later
