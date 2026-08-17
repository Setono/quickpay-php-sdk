<?php

declare(strict_types=1);

namespace Setono\Quickpay\Testing;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * An in-process PSR-18 fake for testing code that uses the SDK — no network, no mocking framework.
 *
 * Script the responses you expect, keyed by request URI, inject the fake as the `Client`'s HTTP
 * client, and assert on `$sentRequests` afterwards:
 *
 * ```
 * $http = (new ScriptedHttpClient())
 *     ->on('payments/1234', $paymentJson)                 // relative to https://api.quickpay.net
 *     ->on('POST payments', $paymentJson, 201)            // optionally pinned to a method
 *     ->on('payments?order_id=o-1&page=1&page_size=1', '[]');
 *
 * $client = new Client('test-key', httpClient: $http);
 * // ... exercise your code ...
 * self::assertSame('PATCH', $http->sentRequests[1]->getMethod());
 * ```
 *
 * A string response becomes a JSON response with the given status and headers, built with the
 * discovered PSR-17 factories (the same ones the `Client` uses); pass a ready-made
 * {@see ResponseInterface} for full control. Keys are matched exactly, so include the query string
 * exactly as the SDK sends it (e.g. `page=1&page_size=20`); when the same URI is scripted with and
 * without a method, the method-specific script wins. A request with no script throws, listing what
 * IS scripted — a missing script is a test-setup mistake, not a 404.
 *
 * Because verification lives at the transport boundary, tests written this way exercise the SDK's
 * real request building, (de)serialization and error mapping — which is why the SDK's own classes
 * are `final` rather than mockable.
 */
final class ScriptedHttpClient implements ClientInterface
{
    private const BASE = 'https://api.quickpay.net/';

    /**
     * Every request dispatched through this fake, in order.
     *
     * @var list<RequestInterface>
     */
    public array $sentRequests = [];

    /** @var array<string, ResponseInterface> */
    private array $scripted = [];

    /**
     * Script the response for a URI.
     *
     * @param string $uri absolute (`https://api.quickpay.net/payments/1`) or relative (`payments/1`)
     *        to the Quickpay API host, optionally prefixed with an HTTP method (`'PATCH payments/1'`)
     * @param ResponseInterface|string $response a full response, or a body string that becomes a
     *        JSON response with `$status` and `$headers`
     * @param array<string, string|string[]> $headers
     */
    public function on(string $uri, ResponseInterface|string $response, int $status = 200, array $headers = []): self
    {
        if (is_string($response)) {
            $response = self::jsonResponse($response, $status, $headers);
        }

        $this->scripted[self::normalize($uri)] = $response;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sentRequests[] = $request;

        $uri = (string) $request->getUri();
        $response = $this->scripted[$request->getMethod() . ' ' . $uri] ?? $this->scripted[$uri] ?? null;

        if (null === $response) {
            throw new \LogicException(sprintf(
                'ScriptedHttpClient has no script for "%s %s". Scripted: %s',
                $request->getMethod(),
                $uri,
                [] === $this->scripted ? '(nothing)' : implode(', ', array_keys($this->scripted)),
            ));
        }

        return $response;
    }

    /**
     * The last request dispatched, or `null` if none was.
     */
    public function lastRequest(): ?RequestInterface
    {
        return $this->sentRequests[array_key_last($this->sentRequests) ?? -1] ?? null;
    }

    /**
     * Build a JSON response with the discovered PSR-17 factories.
     *
     * @param array<string, string|string[]> $headers
     */
    public static function jsonResponse(string $body, int $status = 200, array $headers = []): ResponseInterface
    {
        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse($status)
            ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream($body))
        ;

        foreach (['Content-Type' => 'application/json'] + $headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * `"[METHOD ]uri"` → `"[METHOD ]https://api.quickpay.net/uri"`.
     */
    private static function normalize(string $key): string
    {
        $method = '';
        $uri = $key;

        if (1 === preg_match('/^([A-Z]+) (.+)$/', $key, $m)) {
            $method = $m[1] . ' ';
            $uri = $m[2];
        }

        if (0 === preg_match('#^https?://#i', $uri)) {
            $uri = self::BASE . ltrim($uri, '/');
        }

        return $method . $uri;
    }
}
