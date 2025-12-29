<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect()) // @TODO 4.0 no need to call this manually
    ->setRiskyAllowed(false)
    ->setRules([
        '@auto' => true
    ])
    ->setFinder(
        (new Finder())
            ->in([
                __DIR__ . '/src/php',
                __DIR__ . '/tests/php',
                __DIR__ . '/public',
            ])
            ->name('*.php')
            ->exclude('vendor')
            ->exclude('node_modules')
            ->ignoreDotFiles(true)
            ->ignoreVCSIgnored(true)
    )
;
