<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Payment;

/**
 * Card / payment-method metadata nested on a {@see Payment} (the `metadata` object).
 *
 * Only the most commonly used fields are typed; reach anything else via the parent payment's
 * `$raw['metadata']`. This is a nested DTO and is not `$raw`-stamped itself.
 *
 * Note: `is3dSecure` is typed `?bool` because the live API returns a boolean here, even though the API
 * docs label it a string. The 3-D Secure version, when present, is a separate `3d_secure_type` field
 * reachable via `$raw`.
 */
final class Metadata
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $brand = null,
        public readonly ?string $last4 = null,
        public readonly ?int $expMonth = null,
        public readonly ?int $expYear = null,
        public readonly ?string $country = null,
        public readonly ?bool $is3dSecure = null,
        public readonly ?bool $fraudSuspected = null,
    ) {
    }
}
