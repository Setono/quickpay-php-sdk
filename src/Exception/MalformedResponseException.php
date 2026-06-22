<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown when a 2xx response could not be decoded as JSON, or did not decode to an array.
 *
 * Not `final` — {@see MappingException} narrows it to the "decoded fine but didn't fit the DTO"
 * case, so consumers catching `MalformedResponseException` get both.
 */
class MalformedResponseException extends ResponseAwareException
{
}
