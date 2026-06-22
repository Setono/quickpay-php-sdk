<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Base class for all 4xx Client Error exceptions thrown by this SDK.
 */
abstract class ClientErrorException extends ResponseAwareException
{
}
