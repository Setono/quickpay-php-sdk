<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Payment;

use Setono\Quickpay\Enum\OperationType;

/**
 * An operation recorded against a payment (one item in the payment's `operations` array): an
 * authorize, capture, refund, cancel, etc.
 *
 * `qpStatusCode` is Quickpay's own status code for the operation (`"20000"` means approved);
 * `aqStatusCode` is the acquirer's status code. This is a nested DTO — it is not `$raw`-stamped
 * itself; reach unmodeled fields via the parent payment's `$raw['operations']`.
 */
final class Operation
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?int $amount = null,
        public readonly bool $pending = false,
        public readonly ?string $qpStatusCode = null,
        public readonly ?string $qpStatusMsg = null,
        public readonly ?string $aqStatusCode = null,
        public readonly ?string $aqStatusMsg = null,
        public readonly ?string $callbackUrl = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
    ) {
    }

    /**
     * The operation type as an {@see OperationType}, or `null` if Quickpay returned a value not
     * modeled by the enum.
     */
    public function type(): ?OperationType
    {
        return OperationType::tryFrom($this->type);
    }
}
