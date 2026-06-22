<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments/{id}/authorize`.
 *
 * `amount` is the authorization amount in the payment's currency, expressed in the smallest unit
 * (e.g. cents/øre). Set `autoCapture` to `true` to capture immediately after authorization.
 */
final class AuthorizePayment extends Payload
{
    public function __construct(
        public ?int $amount = null,
        public ?bool $autoCapture = null,
        public ?int $vatAmount = null,
        public ?string $acquirer = null,
    ) {
    }
}
