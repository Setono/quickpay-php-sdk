<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown for 409 Conflict responses — e.g. an operation (capture, refund, cancel) that is not
 * permitted given the payment's current state.
 */
final class ConflictException extends ClientErrorException
{
}
