<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // Virtual packages: they force a concrete PSR-18/PSR-17 implementation to be installed but
    // expose no symbols of their own, so symbol analysis reports them as "unused". That is
    // intentional, so ignore the unused-dependency error for them.
    ->ignoreErrorsOnPackages(
        ['psr/http-client-implementation', 'psr/http-factory-implementation'],
        [ErrorType::UNUSED_DEPENDENCY],
    )
;
