<?php

/**
 * Resolver: create msDelivery record.
 */

use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return true;
}

$modx = $transport->xpdo;

$class = 'Ibochkarev\\Msp3DeliverySkeleton\\Delivery\\SkeletonDelivery';

if ($options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_UNINSTALL) {
    $modx->removeCollection('MiniShop3\\Model\\msDelivery', [
        'class' => $class,
    ]);

    return true;
}

if (
    $options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_INSTALL
    && $options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_UPGRADE
) {
    return true;
}

if (!class_exists('MiniShop3\\Model\\msDelivery')) {
    $modx->log(modX::LOG_LEVEL_WARN, '[msp3DeliverySkeleton] MiniShop3 not found, skipping msDelivery creation');

    return true;
}

$meta = $modx->getFieldMeta('MiniShop3\\Model\\msDelivery');
$precision = isset($meta['class']['precision']) ? (int) $meta['class']['precision'] : 255;
if ($precision > 0 && strlen($class) > $precision) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        '[msp3DeliverySkeleton] FQCN is longer than msDelivery.class (' . $precision . '): ' . $class
    );

    return true;
}

if ($modx->getCount('MiniShop3\\Model\\msDelivery', ['class' => $class])) {
    return true;
}

$delivery = $modx->newObject('MiniShop3\\Model\\msDelivery');
$delivery->fromArray([
    'name' => 'Delivery Skeleton',
    'description' => 'MiniShop3 delivery skeleton. Replace with your carrier.',
    'price' => '0',
    'weight_price' => 0,
    'distance_price' => 0,
    'logo' => '',
    'position' => 0,
    'active' => 0,
    'class' => $class,
    'properties' => [
        'api_url' => '',
        'api_key' => '',
        'account' => '',
        'secret' => '',
        'test_mode' => true,
        'timeout' => 10,
    ],
    'free_delivery_amount' => 0,
], '', true, true);
$delivery->save();
$modx->log(modX::LOG_LEVEL_INFO, '[msp3DeliverySkeleton] Created msDelivery "Delivery Skeleton"');

return true;
