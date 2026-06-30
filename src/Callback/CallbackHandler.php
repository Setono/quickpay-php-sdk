<?php

declare(strict_types=1);

namespace Setono\Quickpay\Callback;

use CuyZ\Valinor\MapperBuilder;
use Psr\Http\Message\ServerRequestInterface;
use Setono\Quickpay\Client\Client;
use Setono\Quickpay\Enum\ResourceType;
use Setono\Quickpay\Exception\InvalidCallbackException;
use Setono\Quickpay\Exception\InvalidChecksumException;

/**
 * Verifies incoming Quickpay callbacks and wraps them as a {@see Callback}.
 *
 * The usual entry point is {@see self::handle()} with the incoming PSR-7 server request: it verifies
 * the checksum and captures the resource-type/account/version headers in one step, reading the raw
 * body once so an unverified body is never trusted. When you only have the raw pieces (e.g. from PHP
 * superglobals, or a framework that already consumed the body stream), use {@see self::handleRaw()}.
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

    public function __construct(string $privateKey, ?MapperBuilder $mapperBuilder = null)
    {
        $this->validator = new CallbackValidator($privateKey);
        $this->mapperBuilder = $mapperBuilder ?? Client::configureMapperBuilder(new MapperBuilder());
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

        $accountId = $request->getHeaderLine(Callback::ACCOUNT_ID_HEADER);
        $apiVersion = $request->getHeaderLine(Callback::API_VERSION_HEADER);

        return new Callback(
            $rawBody,
            self::resolveResourceType($request->getHeaderLine(Callback::RESOURCE_TYPE_HEADER)),
            '' === $accountId ? null : $accountId,
            '' === $apiVersion ? null : $apiVersion,
            $this->mapperBuilder,
        );
    }

    /**
     * Verify the checksum from raw pieces and return the verified {@see Callback}. Use this when you
     * don't have a PSR-7 request — e.g. from `file_get_contents('php://input')` + `$_SERVER`, or a
     * framework that already consumed the body stream.
     *
     * @param string $rawBody the raw, byte-for-byte request body
     * @param string $checksum the value of the `QuickPay-Checksum-Sha256` header
     * @param string $resourceType the value of the `QuickPay-Resource-Type` header
     *
     * @throws InvalidChecksumException if the checksum does not match
     * @throws InvalidCallbackException if the resource type is missing or not a known value
     */
    public function handleRaw(string $rawBody, string $checksum, string $resourceType): Callback
    {
        $this->verify($rawBody, $checksum);

        return new Callback($rawBody, self::resolveResourceType($resourceType), mapperBuilder: $this->mapperBuilder);
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
