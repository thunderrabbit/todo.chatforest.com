<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

// Same paths as phpcs.xml (templates/*.tpl.php are mixed HTML/PHP and out of scope).
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/classes',
        __DIR__ . '/wwwroot',
        __DIR__ . '/prepend.php',
        __DIR__ . '/Tests',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/Tests/_output',
        __DIR__ . '/Tests/Support/_generated',
    ])
    ->withSets([LevelSetList::UP_TO_PHP_83]);
