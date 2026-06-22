<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown when a 2xx response decoded as JSON but did not fit the SDK's typed DTO shape — Valinor
 * raised a `MappingError`.
 *
 * Extends {@see MalformedResponseException} as a narrower case: the body wasn't malformed at the
 * JSON level, but it didn't conform to the DTO contract (unexpected field type, missing required
 * field, version skew between server and SDK). The original `CuyZ\Valinor\Mapper\MappingError` is
 * preserved as the `$previous` exception for full diagnostic detail.
 */
final class MappingException extends MalformedResponseException
{
}
