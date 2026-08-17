<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Setono\Quickpay\Client\Endpoint\PaymentsEndpoint;
use Setono\Quickpay\Exception\InvalidUrlException;
use Setono\Quickpay\Exception\QuickpayException;
use Setono\Quickpay\Exception\TransportException;
use Setono\Quickpay\Request\Payload;

/**
 * The SDK's HTTP layer, as seen by the endpoints and by consumers who want to type-hint or mock it.
 *
 * `Client` is its only implementation. Type-hint against this interface and fake it in tests, but
 * do not implement it in production code: it may gain optional parameters (and, in a major version,
 * methods) as the SDK grows — the backwards-compatibility check baselines exactly such additions
 * (`.roave-backward-compatibility-check.xml`).
 */
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
     * The last response received from the API, or `null` if no response has been received yet (or
     * the last request failed at the transport level).
     */
    public function getLastResponse(): ?ResponseInterface;

    /**
     * Send an arbitrary PSR-7 request to the Quickpay API: the auth, `Accept-Version`, `Accept`
     * and `User-Agent` headers are stamped on, non-2xx responses throw. The request URI MUST point
     * at the Quickpay API host — like every other method here, it goes through the host-pinning
     * guard, so credentials can never be sent anywhere else.
     *
     * @throws InvalidUrlException if the request URI is not on the Quickpay API host (or uses a non-default port)
     * @throws TransportException if the request could not be sent / no response was received (wraps the PSR-18 exception)
     * @throws QuickpayException if the response is non-2xx (concrete subtype depends on the status code)
     */
    public function request(RequestInterface $request): ResponseInterface;

    /**
     * GET the given URI and return the decoded JSON body.
     *
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers extra request headers for this call (the SDK's own —
     *        `Authorization`, `Accept-Version`, `Accept`, `User-Agent` — always win)
     *
     * @return array<array-key, mixed>
     *
     * @throws TransportException if the request could not be sent / no response was received (wraps the PSR-18 exception)
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function get(string $uri, array $query = [], array $headers = []): array;

    /**
     * POST to `$uri` and return the decoded JSON body.
     *
     * `$body` may be a typed {@see Payload} (normalized with snake_case keys and `null`s stripped)
     * or, for endpoints the SDK does not model, a plain array — encoded as given, so use the
     * snake_case keys from the Quickpay docs (nested `Payload` / `\DateTimeInterface` values are
     * still transformed). Leave it out for operations that take no parameters (cancel, renew, …):
     * an empty body is sent as the empty JSON object `{}`, which the API accepts.
     *
     * @param Payload|array<string, mixed> $body
     * @param array<string, string> $headers extra request headers for this call — e.g.
     *        `[Client::CALLBACK_URL_HEADER => 'https://shop.example/callback']` to route an
     *        operation's callback (the SDK's own headers always win)
     *
     * @return array<array-key, mixed>
     *
     * @throws TransportException if the request could not be sent / no response was received (wraps the PSR-18 exception)
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function post(string $uri, Payload|array $body = [], array $headers = []): array;

    /**
     * PUT to `$uri` and return the decoded JSON body. Used for creating (or updating) a payment
     * link. The `$body` is normalized exactly as in {@see self::post()}.
     *
     * @param Payload|array<string, mixed> $body
     * @param array<string, string> $headers extra request headers for this call
     *
     * @return array<array-key, mixed>
     *
     * @throws TransportException if the request could not be sent / no response was received (wraps the PSR-18 exception)
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function put(string $uri, Payload|array $body = [], array $headers = []): array;

    /**
     * PATCH to `$uri` and return the decoded JSON body. Used for updating a payment. The `$body` is
     * normalized exactly as in {@see self::post()}.
     *
     * @param Payload|array<string, mixed> $body
     * @param array<string, string> $headers extra request headers for this call
     *
     * @return array<array-key, mixed>
     *
     * @throws TransportException if the request could not be sent / no response was received (wraps the PSR-18 exception)
     * @throws QuickpayException if the response is non-2xx, or the body is not valid JSON
     */
    public function patch(string $uri, Payload|array $body = [], array $headers = []): array;

    /**
     * DELETE `$uri` and return the decoded JSON body — `[]` for a `204 No Content` response, which
     * is what Quickpay's DELETE endpoints (e.g. `DELETE /payments/{id}/link`) answer.
     *
     * @param array<string, string> $headers extra request headers for this call
     *
     * @return array<array-key, mixed>
     *
     * @throws TransportException if the request could not be sent / no response was received (wraps the PSR-18 exception)
     * @throws QuickpayException if the response is non-2xx, or a non-empty body is not valid JSON
     */
    public function delete(string $uri, array $headers = []): array;

    /**
     * Health check — `GET /ping`. Returns `true` on a 2xx response (a non-2xx response throws).
     *
     * @throws TransportException if the request could not be sent / no response was received (wraps the PSR-18 exception)
     * @throws QuickpayException if the response is non-2xx
     */
    public function ping(): bool;

    public function payments(): PaymentsEndpoint;
}
