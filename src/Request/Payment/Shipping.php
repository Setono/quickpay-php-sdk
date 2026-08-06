<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Shipping/delivery details attached to a payment. Sent as a nested object on create/update;
 * property names are converted to the snake_case keys Quickpay expects (e.g. `trackingNumber` →
 * `tracking_number`). `amount` is in the payment's currency expressed in the smallest unit.
 *
 * All fields are genuinely optional — verified against the live API, which accepts a partial
 * shipping object (e.g. only `amount`) and stores the omitted fields as `null`. Beware `method`:
 * when present it is validated server-side against a fixed set of values (`home_delivery` is
 * accepted; e.g. `pickup` is rejected with `"does not have a valid value"`) — check the Quickpay
 * docs for the accepted list.
 */
final class Shipping extends Payload
{
    public function __construct(
        public ?string $method = null,
        public ?string $company = null,
        public ?int $amount = null,
        public ?float $vatRate = null,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
    ) {
    }
}
