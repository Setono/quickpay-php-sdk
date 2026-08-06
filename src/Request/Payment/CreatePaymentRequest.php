<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments`.
 *
 * `orderId` (4–20 characters) and `currency` are required — verified against the live API, which
 * rejects a create missing either (`order_id` length validation / `currency: "is missing"`). All
 * other fields are optional.
 */
final class CreatePaymentRequest extends Payload
{
    /**
     * @param array<string, mixed> $variables a free-form key/value map stored with the payment
     * @param list<BasketItem> $basket
     */
    public function __construct(
        public string $orderId,
        public string $currency,
        public ?string $textOnStatement = null,
        public ?int $brandingId = null,
        public array $variables = [],
        public ?Address $invoiceAddress = null,
        public ?Address $shippingAddress = null,
        public array $basket = [],
        public ?Shipping $shipping = null,
    ) {
    }
}
