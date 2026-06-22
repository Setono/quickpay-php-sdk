<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client\Endpoint;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use Setono\Quickpay\Client\ClientInterface;
use Setono\Quickpay\Exception\MappingException;
use Setono\Quickpay\Response\Resource;

abstract class Endpoint
{
    public function __construct(
        protected readonly ClientInterface $client,
        protected readonly MapperBuilder $mapperBuilder,
    ) {
    }

    /**
     * Map a single decoded JSON object into a typed {@see Resource} and stamp `$raw` with the
     * untouched payload.
     *
     * Quickpay responses are snake_case while the DTOs use camelCase properties, so the source is
     * run through Valinor's {@see Source::camelCaseKeys()} before mapping. `$raw` keeps the original
     * snake_case body so consumers can reach any field the SDK does not model (matching the keys in
     * the Quickpay API docs).
     *
     * Converts Valinor's `MappingError` (a 2xx body that decoded as JSON but didn't fit the DTO)
     * into the SDK's typed {@see MappingException}, preserving the original as `$previous`.
     *
     * @template T of Resource
     *
     * @param class-string<T> $signature
     * @param array<array-key, mixed> $data
     *
     * @return T
     */
    protected function mapItem(string $signature, array $data): Resource
    {
        try {
            $item = $this->mapperBuilder->mapper()->map($signature, Source::array($data)->camelCaseKeys());

            $item->raw = $data;

            return $item;
        } catch (MappingError $e) {
            $response = $this->client->getLastResponse();
            $request = $this->client->getLastRequest();

            if (null === $response) {
                throw $e;
            }

            $context = null === $request
                ? ''
                : sprintf(' [%s %s]', $request->getMethod(), (string) $request->getUri()->withQuery('')->withFragment(''));

            throw new MappingException(
                $response,
                sprintf('Could not map response body to %s%s: %s', $signature, $context, $e->getMessage()),
                $e,
                request: $request,
            );
        }
    }
}
