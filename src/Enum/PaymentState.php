<?php

declare(strict_types=1);

namespace Setono\Quickpay\Enum;

/**
 * The lifecycle states a Quickpay payment can be in.
 *
 * This enum is treated as NON-exhaustive: {@see \Setono\Quickpay\Response\Payment\Payment::$state}
 * stays a `string` and {@see \Setono\Quickpay\Response\Payment\Payment::state()} resolves it via
 * `tryFrom()`, returning `null` for any value Quickpay introduces that is not modeled here. This
 * way a new server-side state never causes a mapping failure.
 */
enum PaymentState: string
{
    case Initial = 'initial';
    case Pending = 'pending';
    case New = 'new';
    case Rejected = 'rejected';
    case Processed = 'processed';
    case Invalid = 'invalid';
}
