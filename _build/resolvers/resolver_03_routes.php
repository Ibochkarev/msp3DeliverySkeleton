<?php

/**
 * Resolver: install MiniShop3 manager route fragment.
 * INIT:manager — delete this file if manager is not kept.
 */

use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return true;
}

$modx = $transport->xpdo;
$corePath = rtrim((string) $modx->getOption('core_path'), '/') . '/';
$targetDir = $corePath . 'config/ms3.routes.d/manager';
$target = $targetDir . '/50-msp3deliveryskeleton.php';
$source = $corePath . 'components/msp3deliveryskeleton/config/routes.manager.php';

if ($options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_UNINSTALL) {
    if (is_file($target)) {
        unlink($target);
    }

    return true;
}

if (
    $options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_INSTALL
    && $options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_UPGRADE
) {
    return true;
}

if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[msp3DeliverySkeleton] Cannot create ' . $targetDir);

    return true;
}

$stub = <<<'PHP'
<?php

/**
 * Auto-generated include for msp3DeliverySkeleton manager routes.
 */
$component = MODX_CORE_PATH . 'components/msp3deliveryskeleton/config/routes.manager.php';
if (is_file($component)) {
    require $component;
}
PHP;

file_put_contents($target, $stub);
if (!is_file($source)) {
    $modx->log(modX::LOG_LEVEL_WARN, '[msp3DeliverySkeleton] routes.manager.php is missing in the component');
}

return true;
