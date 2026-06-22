<?php

declare(strict_types=1);

namespace Setono\Quickpay\Enum;

/**
 * The type of an operation recorded against a payment (the items in the payment's `operations`
 * array).
 *
 * Treated as NON-exhaustive — see {@see \Setono\Quickpay\Response\Payment\Operation::type()}, which
 * resolves the raw string via `tryFrom()` and returns `null` for unmodeled values.
 */
enum OperationType: string
{
    case Authorize = 'authorize';
    case Capture = 'capture';
    case Refund = 'refund';
    case Cancel = 'cancel';
    case Recurring = 'recurring';
    case Subscribe = 'subscribe';
    case Test = 'test';
}
