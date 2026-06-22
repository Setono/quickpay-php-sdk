<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown for 429 Too Many Requests responses — the API rate limit was exceeded. The response
 * carries a `Retry-After` header, reachable via {@see ResponseAwareException::getResponse()}.
 */
final class TooManyRequestsException extends ClientErrorException
{
}
