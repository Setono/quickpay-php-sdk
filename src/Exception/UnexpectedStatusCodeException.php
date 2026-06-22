<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown for any non-2xx status code that does not map to a more specific exception.
 */
final class UnexpectedStatusCodeException extends ResponseAwareException
{
}
