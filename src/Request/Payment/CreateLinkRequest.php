<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `PUT /payments/{id}/link` — creates (or updates) the payment window link the customer is
 * redirected to.
 *
 * `amount` is required by the API (smallest currency unit). `continueUrl` and `cancelUrl` are where
 * the customer is redirected after a successful / cancelled payment; `callbackUrl` overrides the
 * account's default server-to-server callback URL for this payment. Property names are converted to
 * the snake_case keys Quickpay expects (e.g. `continueUrl` → `continue_url`).
 */
final class CreateLinkRequest extends Payload
{
    public function __construct(
        public ?int $amount = null,
        public ?string $continueUrl = null,
        public ?string $cancelUrl = null,
        public ?string $callbackUrl = null,
        public ?string $language = null,
        public ?string $paymentMethods = null,
        public ?bool $autoFee = null,
        public ?bool $autoCapture = null,
        public ?int $brandingId = null,
        public ?string $googleAnalyticsTrackingId = null,
        public ?string $googleAnalyticsClientId = null,
        public ?string $customerEmail = null,
        public ?int $deadline = null,
        public ?bool $framed = null,
        public ?string $version = null,
        public ?string $acquirer = null,
    ) {
    }
}
