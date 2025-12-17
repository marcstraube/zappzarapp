<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    // Paths to analyze
    $rectorConfig->paths([
        __DIR__ . '/src/php',
        __DIR__ . '/tests',
    ]);

    // Define sets of rules
    $rectorConfig->sets([
        // Update code to PHP 8.4 syntax (e.g., property hooks, new array functions)
        LevelSetList::UP_TO_PHP_84,

        // Improve code quality (dead code removal, type declarations)
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,

        // Optional: Coding Style (if not covered by PHP-CS-Fixer completely)
        SetList::CODING_STYLE,
    ]);

    // Skip some specific files or rules if needed
    $rectorConfig->skip([
        // Example: Skip specific rule in specific file
        // SomeRule::class => [__DIR__ . '/app/src/LegacyFile.php'],
    ]);

    // Use the configured PHPStan cache
    $rectorConfig->cacheDirectory(__DIR__ . '/.rector.cache');
};