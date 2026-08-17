<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Payment;

use Setono\Quickpay\Response\Resource;

/**
 * The payment window link: the `link` object nested on a {@see Payment}, and what
 * `PUT /payments/{id}/link` ({@see \Setono\Quickpay\Client\Endpoint\PaymentsEndpoint::createLink()})
 * returns.
 *
 * `$url` is the URL the customer should be redirected to in order to complete the payment — and it
 * is the ONLY field the `createLink()` response carries (the API answers `{"url": "..."}`), so on
 * that instance every other property is `null` and `$raw` is just `['url' => ...]`. The full link
 * (`amount`, `continueUrl`, `callbackUrl`, …) is available on the payment itself, e.g. via
 * `getById()` → `$payment->link`; fields the SDK does not model are in the payment's `$raw['link']`
 * (nested instances are not `$raw`-stamped).
 *
 * `$autoCapture` tells whether Quickpay captures the payment itself right after authorization (the
 * link was created with `autoCapture: true`), `$autoCaptureAt` the ISO-8601 time of a scheduled
 * capture — so an integration knows whether it must capture or Quickpay already did/will.
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
        public readonly ?bool $autoCapture = null,
        public readonly ?string $autoCaptureAt = null,
    ) {
    }
}
