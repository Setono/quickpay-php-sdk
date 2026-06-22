<?php

declare(strict_types=1);

namespace Setono\Quickpay\Callback;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use Psr\Http\Message\RequestInterface;
use Setono\Quickpay\Client\Client;
use Setono\Quickpay\Exception\InvalidChecksumException;
use Setono\Quickpay\Response\Payment\Payment;

/**
 * Verifies and deserializes incoming Quickpay callbacks.
 *
 * A callback body is a full {@see Payment} object. The recommended flow is to verify the checksum
 * AND deserialize in one call ({@see self::handle()} / {@see self::handleRequest()}) so the raw body
 * is read exactly once and an unverified body is never trusted.
 *
 * Construct with your account's **private key** (Quickpay manager → Settings → Integration), which
 * is different from the API key used by {@see Client}.
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
     * Deserialize a raw callback body into a {@see Payment} WITHOUT verifying the checksum. Prefer
     * {@see self::handle()} so an unauthenticated body is never deserialized.
     *
     * @throws \JsonException if the body is not valid JSON or does not decode to an object
     * @throws MappingError if the decoded body does not fit the Payment DTO
     */
    public function deserialize(string $rawBody): Payment
    {
        /** @var mixed $decoded */
        $decoded = json_decode($rawBody, true, flags: \JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \JsonException(sprintf(
                'Expected the callback body to decode to an object but got %s.',
                get_debug_type($decoded),
            ));
        }

        $payment = $this->mapperBuilder->mapper()->map(Payment::class, Source::array($decoded)->camelCaseKeys());
        $payment->raw = $decoded;

        return $payment;
    }

    /**
     * Verify the checksum and, if valid, deserialize the body into a {@see Payment}.
     *
     * @param string $rawBody the raw, byte-for-byte request body
     * @param string $checksum the value of the `QuickPay-Checksum-Sha256` header
     *
     * @throws InvalidChecksumException if the checksum does not match — the callback is NOT authentic
     * @throws \JsonException if the (verified) body is not valid JSON
     * @throws MappingError if the (verified) body does not fit the Payment DTO
     */
    public function handle(string $rawBody, string $checksum): Payment
    {
        if (!$this->validator->isValid($rawBody, $checksum)) {
            throw new InvalidChecksumException(
                'The callback checksum did not match — the request could not be authenticated.',
            );
        }

        return $this->deserialize($rawBody);
    }

    /**
     * Verify and deserialize a PSR-7 request, reading the raw body and the
     * `QuickPay-Checksum-Sha256` header off it. The body is read once and reused for both steps.
     *
     * @throws InvalidChecksumException if the checksum does not match
     * @throws \JsonException if the verified body is not valid JSON
     * @throws MappingError if the verified body does not fit the Payment DTO
     */
    public function handleRequest(RequestInterface $request): Payment
    {
        return $this->handle(
            (string) $request->getBody(),
            $request->getHeaderLine(CallbackValidator::CHECKSUM_HEADER),
        );
    }
}
