<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments/{id}/refund`.
 *
 * `amount` is the amount to refund, in the payment's currency expressed in the smallest unit
 * (e.g. cents/øre). `extras` is the API's optional hash of acquirer-specific extra parameters; its
 * keys are passed through verbatim (not converted to snake_case).
 */
final class RefundRequest extends Payload
{
    /**
     * @param array<string, mixed> $extras
     */
    public function __construct(
        public ?int $amount = null,
        public array $extras = [],
    ) {
    }
}
