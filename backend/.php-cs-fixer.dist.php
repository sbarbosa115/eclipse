<?php

// PHP-CS-Fixer with the Symfony coding standard (steps/05-static-analysis.md §5.2).
// Check: vendor/bin/php-cs-fixer fix --dry-run --diff   Fix: vendor/bin/php-cs-fixer fix

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor', 'node_modules', 'public'])
    ->notPath('config/reference.php')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
    ])
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setFinder($finder)
;
