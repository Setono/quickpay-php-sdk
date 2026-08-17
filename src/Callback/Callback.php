<?php

declare(strict_types=1);

namespace Setono\Quickpay\Callback;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use Setono\Quickpay\Client\Client;
use Setono\Quickpay\Enum\ResourceType;
use Setono\Quickpay\Exception\InvalidCallbackException;
use Setono\Quickpay\Response\Payment\Payment;

/**
 * A verified Quickpay callback (its checksum has already been validated).
 *
 * A callback body is the resource as it exists after the change (equivalent to `GET /<resource>/<id>`),
 * and Quickpay fires callbacks for more than one resource type — currently payments and subscriptions,
 * identified by the `QuickPay-Resource-Type` header. {@see CallbackHandler} validates that header and
 * rejects anything it doesn't recognize, so {@see self::$type} is always a known {@see ResourceType}.
 *
 * The body is NOT assumed to be a payment: check {@see self::$type} / {@see self::isPayment()} and only
 * call {@see self::payment()} when it is a payment. Use {@see self::toArray()} for any resource type.
 *
 * Instances are produced by {@see CallbackHandler::handle()} / {@see CallbackHandler::handleRaw()}.
 */
final class Callback
{
    public const RESOURCE_TYPE_HEADER = 'QuickPay-Resource-Type';

    public const ACCOUNT_ID_HEADER = 'QuickPay-Account-ID';

    public const API_VERSION_HEADER = 'QuickPay-API-Version';

    /**
     * @param string $body the raw, verified callback body
     * @param ResourceType $type the validated `QuickPay-Resource-Type`
     * @param ?string $accountId the `QuickPay-Account-ID` header (the resource owner's account id)
     * @param ?string $apiVersion the `QuickPay-API-Version` header
     */
    public function __construct(
        public readonly string $body,
        public readonly ResourceType $type,
        public readonly ?string $accountId = null,
        public readonly ?string $apiVersion = null,
        private readonly ?MapperBuilder $mapperBuilder = null,
    ) {
    }

    public function isPayment(): bool
    {
        return ResourceType::Payment === $this->type;
    }

    /**
     * Deserialize the callback body into a {@see Payment}.
     *
     * @throws InvalidCallbackException if this callback is not a payment, or the body is not valid JSON
     *                                  / does not fit the Payment DTO
     */
    public function payment(): Payment
    {
        if (ResourceType::Payment !== $this->type) {
            throw new InvalidCallbackException(sprintf(
                'This callback is a "%s" resource, not a payment — check the type before calling payment().',
                $this->type->value,
            ));
        }

        $decoded = $this->toArray();

        try {
            $payment = $this->mapperBuilder()->mapper()->map(Payment::class, Source::array($decoded)->camelCaseKeys());
        } catch (MappingError $e) {
            throw new InvalidCallbackException('The callback body does not match the expected payment shape: ' . $e->getMessage(), 0, $e);
        }

        $payment->raw = $decoded;

        return $payment;
    }

    /**
     * The decoded callback body as an array, with the original snake_case keys.
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidCallbackException if the body is not valid JSON or does not decode to an object
     */
    public function toArray(): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($this->body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidCallbackException('The callback body is not valid JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new InvalidCallbackException(sprintf(
                'Expected the callback body to decode to an object but got %s.',
                get_debug_type($decoded),
            ));
        }

        return $decoded;
    }

    private function mapperBuilder(): MapperBuilder
    {
        return $this->mapperBuilder ?? Client::defaultMapperBuilder();
    }
}
