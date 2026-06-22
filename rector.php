<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory('./.build/rector');

    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
    ]);

    $rectorConfig->skip([
        // Entry-point Response DTOs stamp `$raw` after Valinor mapping, so the inherited
        // property must stay writable; request `Payload` DTOs are deliberately mutable so a
        // request can be built up incrementally.
        ReadOnlyPropertyRector::class => [
            __DIR__ . '/src/Response',
            __DIR__ . '/src/Request',
        ],
    ]);
};
