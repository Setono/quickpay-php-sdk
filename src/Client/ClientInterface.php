<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Setono\Quickpay\Client\Endpoint\PaymentsEndpoint;
use Setono\Quickpay\Exception\InvalidUrlException;
use Setono\Quickpay\Exception\QuickpayException;
use Setono\Quickpay\Request\Payload;

interface ClientInterface
{
    /**
     * The client-wide default for the `$synchronized` flag on the payment operation methods
     * (authorize/capture/refund/cancel). When an operation method is called with
     * `$synchronized = null` this default decides whether `?synchronized` is appended.
     */
    public function isSynchronized(): bool;

    /**
     * The last request sent to the API, or `null` if no request has been dispatched yet.
     */
    public function getLastRequest(): ?RequestInterface;

    /**
     * The last response received from the API, or `null` if no response has been received yet.
     */
    public function getLastResponse(): ?ResponseInterface;

    /**
     * Send an arbitrary PSR-7 request to the Quickpay API: the auth, `Accept-Version`, `Accept`
     * and `User-Agent` headers are stamped on, non-2xx responses throw. The request URI MUST point
     * at the Quickpay API host — like every other method here, it goes through the host-pinning
     * guard, so credentials can never be sent anywhere else.
     *
     * @throws InvalidUrlException if the request URI is not on the Quickpay API host (or uses a non-default port)
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
     * PUT to `$uri` and return the decoded JSON body. Used for creating (or updating) a payment
     * link. The `$body` is normalized exactly as in {@see self::post()}.
     *
     * @return array<array-key, mixed>
     *
     * @throws ClientExceptionInterface if an error happens while processing the request
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function put(string $uri, ?Payload $body = null): array;

    /**
     * PATCH to `$uri` and return the decoded JSON body. Used for updating a payment. The `$body` is
     * normalized exactly as in {@see self::post()}.
     *
     * @return array<array-key, mixed>
     *
     * @throws ClientExceptionInterface if an error happens while processing the request
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function patch(string $uri, ?Payload $body = null): array;

    /**
     * Health check — `GET /ping`. Returns `true` on a 2xx response (a non-2xx response throws).
     *
     * @throws ClientExceptionInterface if an error happens while processing the request
     * @throws QuickpayException if the response is non-2xx
     */
    public function ping(): bool;

    public function payments(): PaymentsEndpoint;
}
