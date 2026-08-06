<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `PUT /payments/{id}/link` — creates (or updates) the payment window link the customer is
 * redirected to.
 *
 * `amount` (smallest currency unit) is required — verified against the live API, which rejects a
 * link without it (`amount: "is missing"`) and accepts a link with only it. `continueUrl` /
 * `cancelUrl` are where the
 * customer is redirected after a successful / cancelled payment; `callbackUrl` overrides the
 * account's default server-to-server callback URL for this payment. Property names are converted to
 * the snake_case keys Quickpay expects (e.g. `continueUrl` → `continue_url`); `brandingConfig` is an
 * object passed through verbatim.
 */
final class CreateLinkRequest extends Payload
{
    /**
     * @param array<string, mixed> $brandingConfig
     */
    public function __construct(
        public int $amount,
        public ?int $agreementId = null,
        public ?string $language = null,
        public ?string $continueUrl = null,
        public ?string $cancelUrl = null,
        public ?string $callbackUrl = null,
        public ?string $refererUrl = null,
        public ?string $paymentMethods = null,
        public ?bool $autoFee = null,
        public ?bool $autoCapture = null,
        public ?string $autoCaptureAt = null,
        public ?int $brandingId = null,
        public ?string $googleAnalyticsTrackingId = null,
        public ?string $googleAnalyticsClientId = null,
        public ?string $acquirer = null,
        public ?int $deadline = null,
        public ?bool $framed = null,
        public array $brandingConfig = [],
        public ?float $feeVat = null,
        public ?bool $moto = null,
        public ?string $customerEmail = null,
        public ?bool $invoiceAddressSelection = null,
        public ?bool $shippingAddressSelection = null,
    ) {
    }
}
