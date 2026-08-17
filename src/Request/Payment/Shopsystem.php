<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Identifies the shop system / integration creating the payment (`shopsystem[name]` /
 * `shopsystem[version]` on `POST /payments`). Quickpay stores it on the payment's metadata
 * (`shopsystem_name` / `shopsystem_version`) — useful for support and for telling integrations
 * apart in the Quickpay manager, e.g. a plugin sending its own name and version.
 */
final class Shopsystem extends Payload
{
    public function __construct(
        public ?string $name = null,
        public ?string $version = null,
    ) {
    }
}
