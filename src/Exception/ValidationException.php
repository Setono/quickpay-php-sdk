<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown for 400 Bad Request and 422 Unprocessable Entity responses — invalid or missing
 * parameters on a request. The per-field details are available via
 * {@see ResponseAwareException::getValidationErrors()} and the human-readable message via
 * {@see ResponseAwareException::getMessageText()}.
 */
final class ValidationException extends ClientErrorException
{
}
