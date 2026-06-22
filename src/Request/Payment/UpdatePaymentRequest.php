<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `PUT /payments/{id}` — updates the mutable fields of an existing (not yet authorized)
 * payment. Only the properties you set are sent; `null` / empty values are stripped.
 */
final class UpdatePaymentRequest extends Payload
{
    /**
     * @param array<string, mixed> $variables
     * @param list<BasketItem> $basket
     */
    public function __construct(
        public ?string $orderId = null,
        public array $variables = [],
        public array $basket = [],
    ) {
    }
}
