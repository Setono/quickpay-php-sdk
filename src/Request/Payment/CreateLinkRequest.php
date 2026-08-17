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
     * The payment methods / groups the window may offer, as the comma-separated string Quickpay
     * expects (e.g. `"creditcard,mobilepay"`, or `"creditcard,!amex"` to exclude one). Built from
     * the constructor's `$paymentMethods`, which also accepts a list — see there.
     */
    public ?string $paymentMethods;

    /**
     * @param string|list<string>|null $paymentMethods the methods/groups to offer — either
     *        Quickpay's comma-separated string (`"creditcard,!amex,mobilepay"`) or a list of them
     *        (`['creditcard', '!amex', 'mobilepay']`), which is joined for you (a `(string)` cast of
     *        a list would send the literal `Array` and reject every payment); an empty list means
     *        "not set"
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
        string|array|null $paymentMethods = null,
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
        $this->paymentMethods = self::joinPaymentMethods($paymentMethods);
    }

    /**
     * Normalize a list of payment methods/groups to the comma-separated string Quickpay expects;
     * a string passes through and an empty list becomes `null`.
     *
     * @param string|list<string>|null $paymentMethods
     */
    private static function joinPaymentMethods(string|array|null $paymentMethods): ?string
    {
        if (is_array($paymentMethods)) {
            return [] === $paymentMethods ? null : implode(',', $paymentMethods);
        }

        return $paymentMethods;
    }
}
