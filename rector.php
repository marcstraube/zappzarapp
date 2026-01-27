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

        // CODING_STYLE removed: conflicts with PHPStorm inspections and other linters
        // (PHP-CS-Fixer, PHPStan). Focus on code quality and type safety instead.
    ]);

    // Skip files using PHP 8.4 property hooks with asymmetric visibility
    // Rector's constructor promotion doesn't work with `public private(set)`
    // We skip the entire file rather than specific rules, as multiple rules conflict
    $rectorConfig->skip([
        __DIR__ . '/tests/php/App/Unit/Infrastructure/Database/DatabaseConfigStub.php',
        '*/DatabaseConfigStub.php',
    ]);

    // Use the configured PHPStan cache
    $rectorConfig->cacheDirectory(__DIR__ . '/.rector.cache');
};