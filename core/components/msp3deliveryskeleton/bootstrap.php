<?php

/**
 * msp3DeliverySkeleton bootstrap — PSR-4 autoload.
 *
 * @var \MODX\Revolution\modX $modx
 */

$corePath = $modx->getOption(
    'msp3deliveryskeleton_core_path',
    null,
    $modx->getOption('core_path') . 'components/msp3deliveryskeleton/'
);

$srcPath = rtrim((string) $corePath, '/') . '/src/';
spl_autoload_register(static function (string $class) use ($srcPath): bool {
    if (!str_starts_with($class, 'Ibochkarev\\Msp3DeliverySkeleton\\')) {
        return false;
    }
    $relative = substr($class, strlen('Ibochkarev\\Msp3DeliverySkeleton\\'));
    $file = $srcPath . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;

        return true;
    }

    return false;
}, true, true);

if (defined('IN_MANAGER_MODE') && IN_MANAGER_MODE) {
    $modx->lexicon->load('msp3deliveryskeleton:default');
    $modx->lexicon->load('msp3deliveryskeleton:setting');
}
