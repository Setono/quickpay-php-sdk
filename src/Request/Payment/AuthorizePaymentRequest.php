<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments/{id}/authorize`.
 *
 * `amount` (smallest currency unit) is required — verified against the live API, which rejects an
 * authorize without it (`body: "is invalid"`). Authorizing directly via the API generally requires
 * you to supply card data via `$card` (e.g. `['number' => ..., 'expiration' => ..., 'cvd' => ...]`,
 * or a `token`/wallet token) — which puts you in PCI scope. Most integrations instead authorize
 * through the hosted payment window; see
 * {@see \Setono\Quickpay\Client\Endpoint\PaymentsEndpoint::createLink()}.
 *
 * `$card` and `$extras` are hashes passed through verbatim (their inner keys are not snake_cased).
 * Note the API spells the auto-fee flag `autofee` here (without an underscore), unlike the payment
 * link which uses `auto_fee`.
 */
final class AuthorizePaymentRequest extends Payload
{
    /**
     * @param array<string, mixed> $card
     * @param array<string, mixed> $extras
     */
    public function __construct(
        public int $amount,
        public ?bool $autoCapture = null,
        public ?string $autoCaptureAt = null,
        public ?float $vatRate = null,
        public array $card = [],
        public ?string $acquirer = null,
        public ?bool $autofee = null,
        public ?string $customerIp = null,
        public ?bool $zeroAuth = null,
        public ?float $feeVat = null,
        public array $extras = [],
    ) {
    }
}
