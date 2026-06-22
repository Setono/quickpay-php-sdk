<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Payment;

use Setono\Quickpay\Enum\PaymentState;
use Setono\Quickpay\Response\Resource;

/**
 * A Quickpay payment, as returned by the `payments` endpoints and posted to callback URLs.
 *
 * Only the commonly used fields are typed; any field the SDK does not model is reachable via
 * {@see Resource::$raw} using the original snake_case keys (e.g. `$payment->raw['text_on_statement']`).
 *
 * Amounts (`$balance`, `$fee`, operation amounts) are integers in the smallest unit of the
 * payment's currency.
 */
final class Payment extends Resource
{
    /**
     * @param list<Operation> $operations
     */
    public function __construct(
        public readonly int $id,
        public readonly string $orderId,
        public readonly string $currency,
        public readonly string $state,
        public readonly int $merchantId,
        public readonly bool $accepted = false,
        public readonly bool $testMode = false,
        public readonly ?string $ulid = null,
        public readonly ?string $type = null,
        public readonly ?int $balance = null,
        public readonly ?int $fee = null,
        public readonly ?Link $link = null,
        public readonly array $operations = [],
        public readonly ?Metadata $metadata = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null,
    ) {
    }

    /**
     * The payment state as a {@see PaymentState}, or `null` if Quickpay returned a value not
     * modeled by the enum.
     */
    public function state(): ?PaymentState
    {
        return PaymentState::tryFrom($this->state);
    }
}
