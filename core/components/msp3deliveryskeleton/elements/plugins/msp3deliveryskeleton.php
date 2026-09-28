<?php

/**
 * Load msp3DeliverySkeleton autoload and register the MiniShop3 order tab.
 *
 * @var \MODX\Revolution\modX $modx
 * @var array $scriptProperties
 */

$eventName = $modx->event->name ?? '';

$corePath = $modx->getOption(
    'msp3deliveryskeleton_core_path',
    null,
    $modx->getOption('core_path') . 'components/msp3deliveryskeleton/'
);
if (file_exists($corePath . 'bootstrap.php')) {
    require_once $corePath . 'bootstrap.php';
}

// INIT:manager:begin
if ($eventName !== 'msOnManagerCustomCssJs') {
    return;
}

$page = $scriptProperties['page'] ?? '';
if ($page !== 'order') {
    return;
}

$orderId = (int) ($_REQUEST['id'] ?? $_GET['id'] ?? 0);
if ($orderId < 1) {
    return;
}
if (!class_exists(\MiniShop3\Model\msOrder::class) || !class_exists(\MiniShop3\Model\msDelivery::class)) {
    return;
}
$order = $modx->getObject(\MiniShop3\Model\msOrder::class, $orderId);
if (!$order instanceof \MiniShop3\Model\msOrder) {
    return;
}
$delivery = $modx->getObject(\MiniShop3\Model\msDelivery::class, (int) $order->get('delivery_id'));
$deliveryClass = $delivery ? (string) $delivery->get('class') : '';
if ($deliveryClass === '' || !str_starts_with($deliveryClass, 'Ibochkarev\\Msp3DeliverySkeleton\\')) {
    return;
}

$controller = $scriptProperties['controller'] ?? null;
if (!is_object($controller) || !method_exists($controller, 'addHtml')) {
    return;
}

$assetsUrl = $modx->getOption(
    'msp3deliveryskeleton_assets_url',
    null,
    $modx->getOption('assets_url') . 'components/msp3deliveryskeleton/'
);
$modx->lexicon->load('msp3deliveryskeleton:default');
$connectorUrl = $modx->getOption('connector_url', null, $modx->getOption('assets_url') . 'components/minishop3/connector.php');

$config = [
    'assetsUrl' => $assetsUrl,
    'connectorUrl' => $connectorUrl,
    'routePrefix' => '/api/mgr/msp3deliveryskeleton/orders/' . $orderId . '/shipment',
    'lexicon' => [
        'tab_title' => $modx->lexicon('msp3deliveryskeleton.tab_title'),
        'provider' => $modx->lexicon('msp3deliveryskeleton.field_provider'),
        'external_id' => $modx->lexicon('msp3deliveryskeleton.field_external_id'),
        'tracking' => $modx->lexicon('msp3deliveryskeleton.field_tracking'),
        'status' => $modx->lexicon('msp3deliveryskeleton.field_status'),
        'label' => $modx->lexicon('msp3deliveryskeleton.field_label'),
        'create' => $modx->lexicon('msp3deliveryskeleton.action_create'),
        'cancel' => $modx->lexicon('msp3deliveryskeleton.action_cancel'),
        'sync' => $modx->lexicon('msp3deliveryskeleton.action_sync'),
        'empty' => $modx->lexicon('msp3deliveryskeleton.tab_empty'),
        'loading' => $modx->lexicon('msp3deliveryskeleton.tab_loading'),
        'err_generic' => $modx->lexicon('msp3deliveryskeleton.err_generic'),
    ],
];

$controller->addCss($assetsUrl . 'css/mgr/order-tab.css');
$controller->addHtml(
    '<script>window.msp3DeliverySkeletonConfig = ' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>'
    . '<script type="module" src="' . htmlspecialchars($assetsUrl . 'js/mgr/order-tab.js', ENT_QUOTES, 'UTF-8') . '"></script>'
);
// INIT:manager:end
