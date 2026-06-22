<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Setono\Quickpay\Client\Endpoint\PaymentsEndpoint;
use Setono\Quickpay\Exception\QuickpayException;
use Setono\Quickpay\Request\Payload;

interface ClientInterface
{
    /**
     * The last request sent to the API, or `null` if no request has been dispatched yet.
     */
    public function getLastRequest(): ?RequestInterface;

    /**
     * The last response received from the API, or `null` if no response has been received yet.
     */
    public function getLastResponse(): ?ResponseInterface;

    /**
     * @throws ClientExceptionInterface if an error happens while processing the request
     * @throws QuickpayException if the response is non-2xx (concrete subtype depends on the status code)
     */
    public function request(RequestInterface $request): ResponseInterface;

    /**
     * GET the given URI and return the decoded JSON body.
     *
     * @param array<string, scalar|null> $query
     *
     * @return array<array-key, mixed>
     *
     * @throws ClientExceptionInterface if an error happens while processing the request
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function get(string $uri, array $query = []): array;

    /**
     * POST to `$uri` and return the decoded JSON body. When a `$body` is given it is normalized to
     * JSON via the SDK's `NormalizerBuilder` (null-skipping + snake_case keys); when `null` an empty
     * body is sent (for operations such as authorize/cancel that take no parameters).
     *
     * @return array<array-key, mixed>
     *
     * @throws ClientExceptionInterface if an error happens while processing the request
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function post(string $uri, ?Payload $body = null): array;

    /**
     * PUT to `$uri` and return the decoded JSON body. Used for updating a payment and for creating a
     * payment link. The `$body` is normalized exactly as in {@see self::post()}.
     *
     * @return array<array-key, mixed>
     *
     * @throws ClientExceptionInterface if an error happens while processing the request
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function put(string $uri, ?Payload $body = null): array;

    /**
     * Health check — `GET /ping`. Returns `true` on a 2xx response (a non-2xx response throws).
     *
     * @throws ClientExceptionInterface if an error happens while processing the request
     * @throws QuickpayException if the response is non-2xx
     */
    public function ping(): bool;

    public function payments(): PaymentsEndpoint;
}
