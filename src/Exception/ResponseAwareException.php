<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for exceptions that carry an HTTP response from Quickpay.
 *
 * The optional `$body` and `$request` constructor parameters exist to make the lazy-parse getters
 * (`getMessageText()`, `getErrorCode()`, `getValidationErrors()`) robust against non-seekable PSR-7
 * streams. When the body has already been read from the response stream — e.g. when the SDK
 * pre-reads it inside `Client::assertStatusCode()` so it can be passed here — supplying `$body`
 * lets `parseBody()` use the cached text instead of re-reading the stream (which would yield `""`
 * on non-seekable implementations and silently degrade every getter to `null` / `[]`).
 *
 * Supplying `$request` embeds a sanitized `[METHOD URL]` segment in the default message. The URL
 * is sanitized — query string and fragment stripped — so secrets a consumer may have passed via
 * `$query` are not leaked into error messages or logs.
 *
 * Quickpay error bodies have the shape `{"message": "...", "errors": {...}, "error_code": "..."}`.
 */
abstract class ResponseAwareException extends \RuntimeException implements QuickpayException
{
    /** @var array<string, mixed>|null */
    private ?array $parsedBody = null;

    private bool $parsed = false;

    public function __construct(
        private readonly ResponseInterface $response,
        ?string $message = null,
        ?\Throwable $previous = null,
        ?string $body = null,
        ?RequestInterface $request = null,
    ) {
        if (null !== $body) {
            $this->parsed = true;
            $this->parsedBody = self::tryDecode($body);
        }

        if (null === $message) {
            $context = self::buildRequestContext($request);
            $message = sprintf('The status code was: %d.%s', $response->getStatusCode(), $context);

            $bodyText = trim($body ?? (string) $response->getBody());
            if ('' !== $bodyText) {
                $message .= sprintf(' The body was: %s.', $bodyText);
            }

            $message = trim($message);
        }

        parent::__construct($message, 0, $previous);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Quickpay's human-readable `message` field if present.
     */
    public function getMessageText(): ?string
    {
        $body = $this->parseBody();
        if (null === $body) {
            return null;
        }

        $message = $body['message'] ?? null;

        return is_string($message) ? $message : null;
    }

    /**
     * Quickpay's `error_code` field if present. In API v10 this is a string.
     */
    public function getErrorCode(): ?string
    {
        $body = $this->parseBody();
        if (null === $body) {
            return null;
        }

        $code = $body['error_code'] ?? null;

        if (is_string($code)) {
            return $code;
        }

        // Defensive: some responses may serialize the code as a number.
        return is_int($code) ? (string) $code : null;
    }

    /**
     * Quickpay's `errors` document — a map of field name to a list of validation messages. Returns
     * `[]` if absent.
     *
     * @return array<string, mixed>
     */
    public function getValidationErrors(): array
    {
        $body = $this->parseBody();
        if (null === $body) {
            return [];
        }

        $raw = $body['errors'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        /** @var array<string, mixed> $errors */
        $errors = $raw;

        return $errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseBody(): ?array
    {
        if ($this->parsed) {
            return $this->parsedBody;
        }

        $this->parsed = true;

        return $this->parsedBody = self::tryDecode((string) $this->response->getBody());
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function tryDecode(string $body): ?array
    {
        if ('' === trim($body)) {
            return null;
        }

        try {
            $decoded = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $result */
        $result = $decoded;

        return $result;
    }

    /**
     * Build the ` [METHOD URL]` context segment for the default exception message.
     *
     * Strips query string and fragment so any consumer-supplied secrets in query parameters are
     * not exposed in error messages or logs.
     */
    private static function buildRequestContext(?RequestInterface $request): string
    {
        if (null === $request) {
            return '';
        }

        $sanitizedUri = $request->getUri()->withQuery('')->withFragment('');

        return sprintf(' [%s %s]', $request->getMethod(), (string) $sanitizedUri);
    }
}
