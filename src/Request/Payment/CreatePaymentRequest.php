<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Exception\InvalidArgumentException;
use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments`.
 *
 * `orderId` and `currency` are required — verified against the live API, which rejects a create
 * missing either. All other fields are optional. `shopsystem` lets an integration identify itself
 * (name/version) on the payment.
 *
 * `orderId` is validated at construction (see {@see self::ORDER_ID_PATTERN}): the live API accepts
 * 4–20 characters from letters, digits, space, `.`, `_` and `-` — and rejects everything else
 * (`/`, `#`, `:`, `@`, `+`, `,`, `(`, `&`, `=`, `!`, `*`, `'`, `%`, `~`, tab, and any non-ASCII
 * character such as `ø`) under the SAME, misleading message ("must have length between 4 and 20"),
 * so a local check that names the actual rule saves a confusing round-trip. Verified live 2026-08-17.
 * (The property stays a plain public string; only the constructor validates.)
 */
final class CreatePaymentRequest extends Payload
{
    /**
     * What the live API accepts as an `order_id`: 4–20 characters, each a letter, digit, space, `.`,
     * `_` or `-`.
     */
    public const ORDER_ID_PATTERN = '/^[A-Za-z0-9 ._-]{4,20}$/';

    /**
     * @param array<string, mixed> $variables a free-form key/value map stored with the payment
     * @param list<BasketItem> $basket
     *
     * @throws InvalidArgumentException if `$orderId` does not match {@see self::ORDER_ID_PATTERN}
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
        public ?Shopsystem $shopsystem = null,
    ) {
        self::assertValidOrderId($orderId);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertValidOrderId(string $orderId): void
    {
        if (1 !== preg_match(self::ORDER_ID_PATTERN, $orderId)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid order_id "%s": Quickpay accepts 4–20 characters consisting of letters, digits, space, ".", "_" and "-" (got %d character(s)%s).',
                $orderId,
                (int) preg_match_all('/./su', $orderId), // character count without requiring ext-mbstring
                1 === preg_match('/^[A-Za-z0-9 ._-]*$/', $orderId) ? '' : ', including characters outside that set',
            ));
        }
    }
}
