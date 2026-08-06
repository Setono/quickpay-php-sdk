<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments/{id}/refund`.
 *
 * `amount` is the amount to refund, in the payment's currency expressed in the smallest unit
 * (e.g. cents/øre). It is required — verified against the live API, which rejects a refund without
 * it (`body: "is invalid"`) before even checking the transaction state. `vatRate` optionally states
 * the VAT rate of the refunded amount. `extras` is the API's optional hash of acquirer-specific
 * extra parameters; its keys are passed through verbatim.
 */
final class RefundRequest extends Payload
{
    /**
     * @param array<string, mixed> $extras
     */
    public function __construct(
        public int $amount,
        public ?float $vatRate = null,
        public array $extras = [],
    ) {
    }
}
