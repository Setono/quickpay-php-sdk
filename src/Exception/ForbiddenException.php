<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown for 402 Payment Required and 403 Forbidden responses — the authenticated user lacks
 * permission for the operation, or a payment-related precondition was not met.
 */
final class ForbiddenException extends ClientErrorException
{
}
