<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments/{id}/capture`.
 *
 * `amount` is the amount to capture, in the payment's currency expressed in the smallest unit
 * (e.g. cents/øre). It is required — verified against the live API, which rejects a capture without
 * it (`body: "is invalid"`) before even checking the transaction state. `extras` is the API's
 * optional hash of acquirer-specific extra parameters; its keys are passed through verbatim (not
 * converted to snake_case).
 */
final class CaptureRequest extends Payload
{
    /**
     * @param array<string, mixed> $extras
     */
    public function __construct(
        public int $amount,
        public array $extras = [],
    ) {
    }
}
