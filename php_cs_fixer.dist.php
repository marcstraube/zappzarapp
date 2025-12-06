<?php

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__ . '/app/src')
    ->in(__DIR__ . '/tests');

return new PhpCsFixer\Config()
    ->setRules([
        '@PhpCsFixer' => true,
    ])
    ->setFinder($finder);
