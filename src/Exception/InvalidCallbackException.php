<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown when a callback body could not be decoded as JSON, or did not match the expected payment
 * shape.
 *
 * This is the callback-side counterpart to {@see MalformedResponseException} / {@see MappingException}
 * (which carry an HTTP response and therefore don't fit an incoming callback request). A bad checksum
 * is reported separately via {@see InvalidChecksumException}.
 */
final class InvalidCallbackException extends \RuntimeException implements QuickpayException
{
}
