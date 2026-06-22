<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * A single line in a payment's basket. Property names are converted to the snake_case keys Quickpay
 * expects (e.g. `itemNo` → `item_no`).
 */
final class BasketItem extends Payload
{
    public function __construct(
        public ?int $qty = null,
        public ?string $itemNo = null,
        public ?string $itemName = null,
        public ?int $itemPrice = null,
        public ?float $vatRate = null,
    ) {
    }
}
