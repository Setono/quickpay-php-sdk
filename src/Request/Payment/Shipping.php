<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Shipping/delivery details attached to a payment. Sent as a nested object on create/update;
 * property names are converted to the snake_case keys Quickpay expects (e.g. `trackingNumber` →
 * `tracking_number`). `amount` is in the payment's currency expressed in the smallest unit.
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
