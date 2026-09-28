<?php

/**
 * Resolver: create msp3deliveryskeleton system settings and namespace.
 */

use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return true;
}

$modx = $transport->xpdo;

if ($options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_UNINSTALL) {
    $modx->removeCollection('modSystemSetting', ['namespace' => 'msp3deliveryskeleton']);

    return true;
}

if (
    $options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_INSTALL
    && $options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_UPGRADE
) {
    return true;
}

$ns = $modx->getObject('modNamespace', ['name' => 'msp3deliveryskeleton']);
if (!$ns) {
    $ns = $modx->newObject('modNamespace');
    $ns->set('name', 'msp3deliveryskeleton');
    $ns->set('path', '{core_path}components/msp3deliveryskeleton/');
    $ns->set('assets_path', '{assets_path}components/msp3deliveryskeleton/');
    $ns->save();
}

$settings = [
    ['key' => 'msp3deliveryskeleton_api_url', 'value' => '', 'xtype' => 'textfield', 'namespace' => 'msp3deliveryskeleton', 'area' => 'msp3deliveryskeleton'],
    ['key' => 'msp3deliveryskeleton_api_key', 'value' => '', 'xtype' => 'text-password', 'namespace' => 'msp3deliveryskeleton', 'area' => 'msp3deliveryskeleton'],
    ['key' => 'msp3deliveryskeleton_account', 'value' => '', 'xtype' => 'textfield', 'namespace' => 'msp3deliveryskeleton', 'area' => 'msp3deliveryskeleton'],
    ['key' => 'msp3deliveryskeleton_secret', 'value' => '', 'xtype' => 'text-password', 'namespace' => 'msp3deliveryskeleton', 'area' => 'msp3deliveryskeleton'],
    ['key' => 'msp3deliveryskeleton_test_mode', 'value' => true, 'xtype' => 'combo-boolean', 'namespace' => 'msp3deliveryskeleton', 'area' => 'msp3deliveryskeleton'],
    ['key' => 'msp3deliveryskeleton_timeout', 'value' => 10, 'xtype' => 'numberfield', 'namespace' => 'msp3deliveryskeleton', 'area' => 'msp3deliveryskeleton'],
    ['key' => 'msp3deliveryskeleton_debug', 'value' => false, 'xtype' => 'combo-boolean', 'namespace' => 'msp3deliveryskeleton', 'area' => 'msp3deliveryskeleton'],
];

foreach ($settings as $def) {
    if ($modx->getObject('modSystemSetting', ['key' => $def['key']])) {
        continue;
    }
    $obj = $modx->newObject('modSystemSetting');
    $obj->fromArray($def, '', true, true);
    $obj->save();
}

return true;
