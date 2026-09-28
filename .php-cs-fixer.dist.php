<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', '.phpunit.cache'])
    ->notPath('core/components/msp3deliveryskeleton/src/Transport')
    ->notName('*.xml');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder);
