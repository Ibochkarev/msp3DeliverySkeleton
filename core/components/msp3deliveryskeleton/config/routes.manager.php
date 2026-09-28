<?php

/**
 * Manager API fragment for msp3DeliverySkeleton.
 *
 * Copied to core/config/ms3.routes.d/manager/50-msp3deliveryskeleton.php
 *
 * @var \MiniShop3\Router\Router $router
 * @var \MODX\Revolution\modX $modx
 */

use Ibochkarev\Msp3DeliverySkeleton\Manager\ShipmentActions;
use MiniShop3\Router\Middleware\AuthMiddleware;

$auth = [new AuthMiddleware($modx, 'mgr')];

$router->get('/api/mgr/msp3deliveryskeleton/orders/{id}/shipment', static function (array $vars, \MODX\Revolution\modX $modx) {
    return ShipmentActions::get($vars, $modx);
}, $auth);

$router->post('/api/mgr/msp3deliveryskeleton/orders/{id}/shipment/create', static function (array $vars, \MODX\Revolution\modX $modx) {
    return ShipmentActions::create($vars, $modx);
}, $auth);

$router->post('/api/mgr/msp3deliveryskeleton/orders/{id}/shipment/cancel', static function (array $vars, \MODX\Revolution\modX $modx) {
    return ShipmentActions::cancel($vars, $modx);
}, $auth);

$router->post('/api/mgr/msp3deliveryskeleton/orders/{id}/shipment/sync', static function (array $vars, \MODX\Revolution\modX $modx) {
    return ShipmentActions::sync($vars, $modx);
}, $auth);
