<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect()) // @TODO 4.0 no need to call this manually
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS:risky' => true,
        'binary_operator_spaces' => [
            'operators' => ['=>' => 'align', '=' => 'align'],
        ],
        // Enforce qualified imports (use statements) instead of inline FQCN
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
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
