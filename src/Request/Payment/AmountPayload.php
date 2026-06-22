<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for the `capture` and `refund` operations, which both take an `amount` (in the payment's
 * currency, smallest unit) and optionally an `acquirer`.
 */
final class AmountPayload extends Payload
{
    public function __construct(
        public ?int $amount = null,
        public ?string $acquirer = null,
    ) {
    }
}
