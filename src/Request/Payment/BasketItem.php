<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * A single line in a payment's basket. Property names are converted to the snake_case keys Quickpay
 * expects (e.g. `itemNo` → `item_no`).
 *
 * All five fields are required — verified against the live API, which treats a basket item as
 * all-or-nothing: any partial item is rejected with a per-field `"is missing"` error for every
 * omitted field. (An all-empty item is also dangerous on the wire: it would serialize as `[]`, a
 * shape that triggers an HTTP 500 on Quickpay's side — required fields make that unrepresentable.)
 *
 * `itemPrice` is per item, in the payment's currency expressed in the smallest unit.
 */
final class BasketItem extends Payload
{
    public function __construct(
        public int $qty,
        public string $itemNo,
        public string $itemName,
        public int $itemPrice,
        public float $vatRate,
    ) {
    }
}
