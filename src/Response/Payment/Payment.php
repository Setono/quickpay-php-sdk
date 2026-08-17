<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Payment;

use Setono\Quickpay\Enum\OperationType;
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
 *
 * What happened to a payment is recorded in its `$operations` (authorize, capture, refund, cancel,
 * …), each with a `pending` flag and Quickpay status code. The helpers below answer the usual
 * questions without hand-rolling that inspection: {@see self::authorizedAmount()},
 * {@see self::capturedAmount()}, {@see self::refundedAmount()}, {@see self::isCancelled()},
 * {@see self::hasPendingOperation()}, {@see self::latestOperation()}, {@see self::operation()} and
 * {@see self::operationsOfType()}. Note `$accepted` is Quickpay's own "authorization accepted by
 * the acquirer" flag and `$balance` its captured-minus-refunded balance.
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

    /**
     * The operation with the given id, or `null`. Operation ids are numbered per payment
     * (`1`, `2`, …), which makes them a natural idempotency key when processing callbacks.
     */
    public function operation(int $id): ?Operation
    {
        foreach ($this->operations as $operation) {
            if ($operation->id === $id) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * The most recent operation (the one with the highest id), or `null` if there is none yet.
     * Handy in a callback handler: the latest operation is usually the one that triggered it.
     */
    public function latestOperation(): ?Operation
    {
        $latest = null;
        foreach ($this->operations as $operation) {
            if (null === $latest || $operation->id > $latest->id) {
                $latest = $operation;
            }
        }

        return $latest;
    }

    /**
     * All operations of the given type (`OperationType::Capture` or `'capture'`), in the order
     * Quickpay returned them.
     *
     * @return list<Operation>
     */
    public function operationsOfType(OperationType|string $type): array
    {
        return array_values(array_filter(
            $this->operations,
            static fn (Operation $operation): bool => $operation->isOfType($type),
        ));
    }

    /**
     * Whether any operation is still being processed. When Quickpay runs an operation
     * asynchronously (the default) the payment returned by capture/refund/cancel is only a snapshot
     * taken when the operation was queued — poll `getById()` or wait for the callback until this
     * is `false` before reading the outcome.
     */
    public function hasPendingOperation(): bool
    {
        foreach ($this->operations as $operation) {
            if ($operation->pending) {
                return true;
            }
        }

        return false;
    }

    /**
     * The total amount authorized on this payment: the sum of all APPROVED `authorize` operations
     * (and `recurring` ones, which authorize a recurring payment). `0` if the payment was never
     * (successfully) authorized.
     */
    public function authorizedAmount(): int
    {
        return $this->approvedAmount(OperationType::Authorize) + $this->approvedAmount(OperationType::Recurring);
    }

    /**
     * The total amount captured so far: the sum of all APPROVED `capture` operations.
     */
    public function capturedAmount(): int
    {
        return $this->approvedAmount(OperationType::Capture);
    }

    /**
     * The total amount refunded so far: the sum of all APPROVED `refund` operations.
     */
    public function refundedAmount(): int
    {
        return $this->approvedAmount(OperationType::Refund);
    }

    /**
     * Whether the authorization has been cancelled (voided): there is an APPROVED `cancel`
     * operation.
     */
    public function isCancelled(): bool
    {
        foreach ($this->operationsOfType(OperationType::Cancel) as $operation) {
            if ($operation->isApproved()) {
                return true;
            }
        }

        return false;
    }

    private function approvedAmount(OperationType $type): int
    {
        $sum = 0;
        foreach ($this->operationsOfType($type) as $operation) {
            if ($operation->isApproved()) {
                $sum += $operation->amount ?? 0;
            }
        }

        return $sum;
    }
}
