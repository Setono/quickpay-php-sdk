<?php

declare(strict_types=1);

namespace Setono\Quickpay\Callback;

use CuyZ\Valinor\Cache\Cache;
use CuyZ\Valinor\MapperBuilder;
use Psr\Http\Message\ServerRequestInterface;
use Setono\Quickpay\Client\Client;
use Setono\Quickpay\Enum\ResourceType;
use Setono\Quickpay\Exception\InvalidCallbackException;
use Setono\Quickpay\Exception\InvalidChecksumException;

/**
 * Verifies incoming Quickpay callbacks and wraps them as a {@see Callback}.
 *
 * Three entry points, all returning the same verified {@see Callback}:
 *  - {@see self::handle()} — a PSR-7 server request (PSR-15 apps, or via a PSR-7 bridge).
 *  - {@see self::handleRaw()} — the raw body + header values you already have (Symfony / Laravel
 *    `Request`, or a framework that consumed the body stream).
 *  - {@see self::handleGlobals()} — plain PHP: `php://input` + `$_SERVER`.
 * Each reads the raw body once and verifies it before anything else, so an unverified body is never
 * trusted.
 *
 * The `QuickPay-Resource-Type` header is required and must be one of the known {@see ResourceType}
 * values — an unexpected or missing value is rejected with an {@see InvalidCallbackException}.
 *
 * Construct with your account's **private key** (Quickpay manager → Settings → Integration), which is
 * different from the API key used by {@see Client}.
 */
final class CallbackHandler
{
    private readonly CallbackValidator $validator;

    private readonly MapperBuilder $mapperBuilder;

    /**
     * @param MapperBuilder|null $mapperBuilder a custom, already SDK-configured builder (see
     *        {@see Client::configureMapperBuilder()}); usually left `null`
     * @param Cache|null $cache a Valinor cache for the default builder (the recommended production
     *        setup, e.g. the same `FileSystemCache` you give the `Client`); ignored when a
     *        `$mapperBuilder` is passed
     */
    public function __construct(string $privateKey, ?MapperBuilder $mapperBuilder = null, ?Cache $cache = null)
    {
        $this->validator = new CallbackValidator($privateKey);
        $this->mapperBuilder = $mapperBuilder ?? Client::defaultMapperBuilder($cache);
    }

    public function validator(): CallbackValidator
    {
        return $this->validator;
    }

    /**
     * Verify the incoming callback request and return the verified {@see Callback}, reading the raw
     * body and the `QuickPay-*` headers off it. The body is read once and reused.
     *
     * @throws InvalidChecksumException if the checksum does not match — the callback is NOT authentic
     * @throws InvalidCallbackException if the resource type is missing or not a known value
     */
    public function handle(ServerRequestInterface $request): Callback
    {
        $rawBody = (string) $request->getBody();

        $this->verify($rawBody, $request->getHeaderLine(CallbackValidator::CHECKSUM_HEADER));

        return new Callback(
            $rawBody,
            self::resolveResourceType($request->getHeaderLine(Callback::RESOURCE_TYPE_HEADER)),
            self::nullIfEmpty($request->getHeaderLine(Callback::ACCOUNT_ID_HEADER)),
            self::nullIfEmpty($request->getHeaderLine(Callback::API_VERSION_HEADER)),
            $this->mapperBuilder,
        );
    }

    /**
     * Verify the checksum from raw pieces and return the verified {@see Callback}. Use this when you
     * don't have a PSR-7 request — a Symfony/Laravel `Request`, or a framework that already consumed
     * the body stream:
     *
     * ```
     * $callback = $handler->handleRaw(
     *     $request->getContent(),                                  // Symfony; Laravel: $request->getContent()
     *     (string) $request->headers->get('QuickPay-Checksum-Sha256'), // Laravel: $request->header(...)
     *     (string) $request->headers->get('QuickPay-Resource-Type'),
     *     accountId: $request->headers->get('QuickPay-Account-ID'),
     *     apiVersion: $request->headers->get('QuickPay-API-Version'),
     * );
     * ```
     *
     * For plain PHP (superglobals) see {@see self::handleGlobals()}.
     *
     * @param string $rawBody the raw, byte-for-byte request body
     * @param string $checksum the value of the `QuickPay-Checksum-Sha256` header
     * @param string $resourceType the value of the `QuickPay-Resource-Type` header
     * @param string|null $accountId the value of the `QuickPay-Account-ID` header, if you have it
     * @param string|null $apiVersion the value of the `QuickPay-API-Version` header, if you have it
     *
     * @throws InvalidChecksumException if the checksum does not match
     * @throws InvalidCallbackException if the resource type is missing or not a known value
     */
    public function handleRaw(
        string $rawBody,
        string $checksum,
        string $resourceType,
        ?string $accountId = null,
        ?string $apiVersion = null,
    ): Callback {
        $this->verify($rawBody, $checksum);

        return new Callback(
            $rawBody,
            self::resolveResourceType($resourceType),
            self::nullIfEmpty($accountId),
            self::nullIfEmpty($apiVersion),
            $this->mapperBuilder,
        );
    }

    /**
     * Verify the callback from PHP's superglobals — the raw body from `php://input` and the
     * `QuickPay-*` headers from `$_SERVER` (`HTTP_QUICKPAY_CHECKSUM_SHA256`, …) — and return the
     * verified {@see Callback}. For plain-PHP endpoints without a PSR-7 request or a framework:
     *
     * ```
     * $callback = (new CallbackHandler($privateKey))->handleGlobals();
     * ```
     *
     * `php://input` can only be read once per request by some SAPIs; if your code has already read
     * it, pass the body you read as `$rawBody`. `$server` defaults to `$_SERVER` (inject an array in
     * tests).
     *
     * @param string|null $rawBody the raw request body; `null` reads `php://input`
     * @param array<string, mixed>|null $server the server variables; `null` uses `$_SERVER`
     *
     * @throws InvalidChecksumException if the checksum does not match
     * @throws InvalidCallbackException if the resource type is missing or not a known value
     */
    public function handleGlobals(?string $rawBody = null, ?array $server = null): Callback
    {
        $rawBody ??= (string) file_get_contents('php://input');
        if (null === $server) {
            /** @var array<string, mixed> $server */
            $server = $_SERVER;
        }

        return $this->handleRaw(
            $rawBody,
            self::serverHeader($server, CallbackValidator::CHECKSUM_HEADER),
            self::serverHeader($server, Callback::RESOURCE_TYPE_HEADER),
            self::serverHeader($server, Callback::ACCOUNT_ID_HEADER),
            self::serverHeader($server, Callback::API_VERSION_HEADER),
        );
    }

    /**
     * @throws InvalidChecksumException
     */
    private function verify(string $rawBody, string $checksum): void
    {
        if (!$this->validator->isValid($rawBody, $checksum)) {
            throw new InvalidChecksumException(
                'The callback checksum did not match — the request could not be authenticated.',
            );
        }
    }

    /**
     * The value of an HTTP header as PHP exposes it in `$_SERVER` (`QuickPay-Checksum-Sha256` →
     * `HTTP_QUICKPAY_CHECKSUM_SHA256`), or `''` when absent.
     *
     * @param array<string, mixed> $server
     */
    private static function serverHeader(array $server, string $header): string
    {
        $value = $server['HTTP_' . strtoupper(str_replace('-', '_', $header))] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        return null === $value || '' === $value ? null : $value;
    }

    /**
     * @throws InvalidCallbackException if the resource type is missing or not a known value
     */
    private static function resolveResourceType(string $resourceType): ResourceType
    {
        $type = ResourceType::tryFrom($resourceType);
        if (null === $type) {
            throw new InvalidCallbackException(sprintf(
                'Unexpected QuickPay-Resource-Type "%s"; expected one of: %s.',
                $resourceType,
                implode(', ', array_map(static fn (ResourceType $t): string => $t->value, ResourceType::cases())),
            ));
        }

        return $type;
    }
}
