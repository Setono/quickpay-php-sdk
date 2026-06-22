<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `PATCH /payments/{id}` — updates the mutable fields of an existing (not yet authorized)
 * payment. Only the properties you set are sent; `null` / empty values are stripped.
 *
 * Note: the API does not allow changing `order_id` or `basket` via update — those are only settable
 * on creation — so they are intentionally absent here. `deadlineAt` is an ISO-8601 timestamp string
 * by which the payment must be authorized.
 */
final class UpdatePaymentRequest extends Payload
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        public ?string $deadlineAt = null,
        public ?Address $invoiceAddress = null,
        public ?Address $shippingAddress = null,
        public ?Shipping $shipping = null,
        public array $variables = [],
    ) {
    }
}
