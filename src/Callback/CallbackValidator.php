<?php

declare(strict_types=1);

namespace Setono\Quickpay\Callback;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Validates the authenticity of an incoming Quickpay callback (webhook).
 *
 * Quickpay signs every callback by computing `hash_hmac('sha256', rawRequestBody, privateKey)` and
 * sending the result in the `QuickPay-Checksum-Sha256` header. The checksum MUST be computed over
 * the **raw, byte-for-byte request body** — do NOT decode and re-encode the JSON first, or the
 * checksum will not match.
 *
 * The private key is your account's private key (Quickpay manager → Settings → Integration), which
 * is DIFFERENT from the API key used to authenticate API requests.
 */
final class CallbackValidator
{
    public const CHECKSUM_HEADER = 'QuickPay-Checksum-Sha256';

    public function __construct(private readonly string $privateKey)
    {
    }

    /**
     * Compute the expected checksum for the given raw body. Useful for testing and for callers that
     * want to compare it themselves.
     */
    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $this->privateKey);
    }

    /**
     * Whether `$checksum` (the value of the `QuickPay-Checksum-Sha256` header) matches the HMAC of
     * `$rawBody`. Uses {@see hash_equals()} for a timing-safe comparison.
     */
    public function isValid(string $rawBody, string $checksum): bool
    {
        return hash_equals($this->sign($rawBody), $checksum);
    }

    /**
     * Validate a PSR-7 request directly, reading the raw body and the checksum header off it.
     *
     * Note: reading the body consumes the PSR-7 stream. If you need the body again afterwards (e.g.
     * to deserialize it with {@see CallbackHandler}), capture `(string) $request->getBody()` once
     * and pass that string to both {@see self::isValid()} and the handler, or rewind the stream.
     */
    public function isValidRequest(ServerRequestInterface $request): bool
    {
        return $this->isValid(
            (string) $request->getBody(),
            $request->getHeaderLine(self::CHECKSUM_HEADER),
        );
    }
}
