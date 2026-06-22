<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments`.
 *
 * `orderId` and `currency` are required by the Quickpay API. Following the SDK's `Payload`
 * convention they are nullable with a `null` default (so a request can be built incrementally);
 * omitting them surfaces as a `ValidationException` from the API rather than a construction-time
 * error.
 */
final class CreatePaymentRequest extends Payload
{
    /**
     * @param array<string, mixed> $variables a free-form key/value map stored with the payment
     * @param list<BasketItem> $basket
     */
    public function __construct(
        public ?string $orderId = null,
        public ?string $currency = null,
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
