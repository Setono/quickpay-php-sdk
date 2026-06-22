<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments/{id}/refund`. `amount` is the amount to refund, in the payment's
 * currency expressed in the smallest unit (e.g. cents/øre).
 */
final class RefundRequest extends Payload
{
    public function __construct(
        public ?int $amount = null,
        public ?string $acquirer = null,
    ) {
    }
}
