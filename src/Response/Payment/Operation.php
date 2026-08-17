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
 *
 * Reading the outcome: while `pending` is `true` the operation is still being processed and the
 * status codes say nothing yet. Once it has completed, {@see self::isApproved()} tells you whether
 * it succeeded; any other completed outcome (rejected by the acquirer, 3-D Secure required, gateway
 * error, …) is described by `qpStatusCode` / `qpStatusMsg` — see the Quickpay status-code appendix.
 */
final class Operation
{
    /**
     * The `qp_status_code` Quickpay assigns to an approved (successful) operation.
     */
    public const QP_STATUS_APPROVED = '20000';

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

    /**
     * Whether this operation is of the given type (`OperationType::Capture` or `'capture'`).
     */
    public function isOfType(OperationType|string $type): bool
    {
        return $this->type === ($type instanceof OperationType ? $type->value : $type);
    }

    /**
     * Whether the operation has completed successfully: it is no longer pending AND Quickpay's
     * status code is `20000` (approved). `false` for a pending operation and for every failed or
     * inconclusive outcome (rejected, 3-D Secure required, gateway error, …).
     */
    public function isApproved(): bool
    {
        return !$this->pending && self::QP_STATUS_APPROVED === $this->qpStatusCode;
    }
}
