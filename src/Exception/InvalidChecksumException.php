<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown when a callback's `QuickPay-Checksum-Sha256` header does not match the HMAC-SHA256 of the
 * raw request body computed with the account's private key — i.e. the callback could not be
 * authenticated and MUST NOT be trusted.
 */
final class InvalidChecksumException extends \RuntimeException implements QuickpayException
{
}
