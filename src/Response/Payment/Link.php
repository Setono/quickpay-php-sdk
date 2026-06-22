<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Payment;

use Setono\Quickpay\Response\Resource;

/**
 * The payment window link returned by `PUT /payments/{id}/link`, and the `link` object nested on a
 * {@see Payment}.
 *
 * `$url` is the URL the customer should be redirected to in order to complete the payment. Any field
 * the SDK does not model is reachable via {@see Resource::$raw} (only populated when the `Link` is
 * returned directly from `createLink()`; when nested on a `Payment`, reach it via the payment's
 * `$raw['link']`).
 */
final class Link extends Resource
{
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?int $amount = null,
        public readonly ?string $language = null,
        public readonly ?string $continueUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly ?string $callbackUrl = null,
    ) {
    }
}
