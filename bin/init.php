<?php

declare(strict_types=1);

/**
 * Rename msp3DeliverySkeleton into a new extra.
 *
 * php bin/init.php --name=msp3Cdek --provider=Cdek [--keep=shipment,webhook,manager]
 */

require_once __DIR__ . '/Generator.php';

$root = dirname(__DIR__);
$cliArgs = $_SERVER['argv'] ?? [];

try {
    $opts = Msp3DeliverySkeletonGenerator::parseArgv(is_array($cliArgs) ? $cliArgs : []);
    $generator = new Msp3DeliverySkeletonGenerator($root, $opts);
    exit($generator->run());
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
