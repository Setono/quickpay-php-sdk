<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown for 401 Unauthorized responses — typically an invalid or missing API key.
 */
final class UnauthorizedException extends ClientErrorException
{
}
