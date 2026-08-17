<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client;

use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Setono\Quickpay\Client\Endpoint\PaymentsEndpoint;
use Setono\Quickpay\Exception\ConflictException;
use Setono\Quickpay\Exception\ForbiddenException;
use Setono\Quickpay\Exception\InternalServerErrorException;
use Setono\Quickpay\Exception\InvalidUrlException;
use Setono\Quickpay\Exception\MalformedResponseException;
use Setono\Quickpay\Exception\MethodNotAllowedException;
use Setono\Quickpay\Exception\NotFoundException;
use Setono\Quickpay\Exception\TooManyRequestsException;
use Setono\Quickpay\Exception\TransportException;
use Setono\Quickpay\Exception\UnauthorizedException;
use Setono\Quickpay\Exception\UnexpectedStatusCodeException;
use Setono\Quickpay\Exception\ValidationException;
use Setono\Quickpay\Request\Payload;

final class Client implements ClientInterface
{
    /**
     * The Quickpay API version this SDK targets, sent in the mandatory `Accept-Version` header.
     */
    public const API_VERSION = 'v10';

    private const HOST = 'https://api.quickpay.net';

    private ?RequestInterface $lastRequest = null;

    private ?ResponseInterface $lastResponse = null;

    private ?PaymentsEndpoint $paymentsEndpoint = null;

    private readonly HttpClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    private readonly MapperBuilder $mapperBuilder;

    private readonly NormalizerBuilder $normalizerBuilder;

    /**
     * @param bool $synchronized the client-wide default for the `$synchronized` flag on the payment
     *        operation methods (authorize/capture/refund/cancel); a non-null per-call argument
     *        overrides it
     */
    public function __construct(
        private readonly string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?MapperBuilder $mapperBuilder = null,
        ?NormalizerBuilder $normalizerBuilder = null,
        private readonly bool $synchronized = false,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->mapperBuilder = $mapperBuilder ?? self::defaultMapperBuilder();
        $this->normalizerBuilder = $normalizerBuilder ?? self::defaultNormalizerBuilder();
    }

    public function isSynchronized(): bool
    {
        return $this->synchronized;
    }

    public function getLastRequest(): ?RequestInterface
    {
        return $this->lastRequest;
    }

    public function getLastResponse(): ?ResponseInterface
    {
        return $this->lastResponse;
    }

    public function request(RequestInterface $request): ResponseInterface
    {
        // Every request — including consumer-built ones passed straight in — goes through the
        // host-pinning guard, so the API key below is never stamped onto a request for another host.
        self::assertAllowedUrl((string) $request->getUri());

        // Quickpay uses HTTP Basic auth with an empty username and the API key as the password.
        $request = $request
            ->withHeader('Authorization', sprintf('Basic %s', base64_encode(':' . $this->apiKey)))
            ->withHeader('Accept-Version', self::API_VERSION)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent())
        ;

        if (!$request->hasHeader('Content-Type') && in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Nothing came back: record the attempt, then surface it as an SDK exception.
            $this->lastRequest = $request;
            $this->lastResponse = null;

            throw new TransportException($request, $e);
        }

        $this->lastRequest = $request;
        $this->lastResponse = $response;

        self::assertStatusCode($request, $response);

        return $response;
    }

    public function get(string $uri, array $query = []): array
    {
        $request = $this->requestFactory->createRequest('GET', $this->resolveUrl($uri, $query));

        return self::decodeJson($request, $this->request($request));
    }

    public function post(string $uri, Payload|array|null $body = null): array
    {
        return $this->send('POST', $uri, $body);
    }

    public function put(string $uri, Payload|array|null $body = null): array
    {
        return $this->send('PUT', $uri, $body);
    }

    public function patch(string $uri, Payload|array|null $body = null): array
    {
        return $this->send('PATCH', $uri, $body);
    }

    public function delete(string $uri): array
    {
        $request = $this->requestFactory->createRequest('DELETE', $this->resolveUrl($uri));

        return self::decodeJson($request, $this->request($request));
    }

    public function ping(): bool
    {
        $this->get('ping');

        return true;
    }

    public function payments(): PaymentsEndpoint
    {
        return $this->paymentsEndpoint ??= new PaymentsEndpoint($this, $this->mapperBuilder);
    }

    /**
     * Apply the SDK's full mapper configuration to a consumer-supplied {@see MapperBuilder}. This is
     * the entry point consumers SHOULD use when wiring a custom builder (e.g. with a `FileSystemCache`
     * for production):
     *
     * ```
     * $custom = Client::configureMapperBuilder((new MapperBuilder())->withCache($cache));
     * $client = new Client('API_KEY', mapperBuilder: $custom);
     * ```
     */
    public static function configureMapperBuilder(MapperBuilder $builder): MapperBuilder
    {
        return $builder
            ->allowScalarValueCasting()
            ->allowNonSequentialList()
            ->allowSuperfluousKeys()
            ->supportDateFormats(
                'Y-m-d\TH:i:sP', // e.g. "2018-10-17T13:25:44Z" (P accepts the Z suffix)
                'Y-m-d\TH:i:s.uP', // e.g. "2018-10-17T13:25:44.557Z" / "...+02:00"
            )
        ;
    }

    /**
     * Append the SDK's `Payload` normalizer transformer (null-skipping + snake_case keys) and the
     * `\DateTimeInterface` → DATE_ATOM transformer to a consumer-supplied {@see NormalizerBuilder}.
     * Use this when wiring a custom builder (e.g. with a cache):
     *
     * ```
     * $custom = Client::registerNormalizerTransformers((new NormalizerBuilder())->withCache($cache));
     * $client = new Client('API_KEY', normalizerBuilder: $custom);
     * ```
     */
    public static function registerNormalizerTransformers(NormalizerBuilder $builder): NormalizerBuilder
    {
        return $builder
            ->registerTransformer(
                static fn (\DateTimeInterface $date): string => $date->format(\DATE_ATOM),
            )
            ->registerTransformer(
                /**
                 * @return array<string, mixed>
                 */
                static function (Payload $payload, callable $next): array {
                    /** @var array<string, mixed> $normalized */
                    $normalized = $next();

                    $result = [];
                    foreach ($normalized as $key => $value) {
                        // Strip both `null` AND `[]` so optional properties and empty collections
                        // are omitted from the JSON rather than serialized as `null` / `[]`.
                        if (null === $value || [] === $value) {
                            continue;
                        }

                        $result[self::camelToSnake($key)] = $value;
                    }

                    return $result;
                },
            )
        ;
    }

    /**
     * @param Payload|array<string, mixed>|null $body
     *
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $uri, Payload|array|null $body): array
    {
        $request = $this->requestFactory->createRequest($method, $this->resolveUrl($uri));

        if (null !== $body) {
            // Both shapes go through the SDK's normalizer: a Payload gets snake_cased + null-stripped
            // by the Payload transformer; a plain array is encoded as given (its keys untouched), but
            // any Payload / \DateTimeInterface values nested inside it are still transformed.
            $json = $this->normalizerBuilder->normalizer(Format::json())->normalize($body);

            // A Payload whose optional fields are all unset (or an empty array) normalizes to an
            // empty PHP array, which JSON-encodes as `[]` — the Quickpay API rejects that shape
            // (`body: "is invalid"`); an empty body must be the empty JSON object.
            if ('[]' === $json) {
                $json = '{}';
            }

            $request = $request->withBody($this->streamFactory->createStream($json));
        }

        return self::decodeJson($request, $this->request($request));
    }

    private static function defaultMapperBuilder(): MapperBuilder
    {
        return self::configureMapperBuilder(new MapperBuilder());
    }

    private static function defaultNormalizerBuilder(): NormalizerBuilder
    {
        return self::registerNormalizerTransformers(new NormalizerBuilder());
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function resolveUrl(string $uri, array $query = []): string
    {
        if (1 === preg_match('#^https?://#i', $uri)) {
            self::assertAllowedUrl($uri);

            if ([] !== $query) {
                throw new InvalidUrlException(
                    'The $query parameter cannot be combined with an absolute URL — the URL already encodes its own query string.',
                );
            }

            return $uri;
        }

        $url = sprintf('%s/%s', self::HOST, ltrim($uri, '/'));

        if ([] !== $query) {
            $url .= '?' . http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * The credential-leak guard: refuse any absolute URL that does not point at the Quickpay API
     * host on its default port. Applied both when the SDK builds a URL from a path
     * ({@see self::resolveUrl()}) and to every request that reaches {@see self::request()}, so the
     * API key is never sent anywhere else — not even for consumer-built requests.
     *
     * @throws InvalidUrlException
     */
    private static function assertAllowedUrl(string $url): void
    {
        // RFC 3986 hosts are case-insensitive — normalize both sides. The SDK refuses to send its
        // auth credentials to any host other than the Quickpay API host.
        $baseHost = strtolower(self::parseStringPart(self::HOST, \PHP_URL_HOST));
        $urlHost = strtolower(self::parseStringPart($url, \PHP_URL_HOST));

        if ($baseHost !== $urlHost) {
            throw new InvalidUrlException(sprintf(
                'Refusing to send a request to host "%s" — the Quickpay base host is "%s". '
                . 'The SDK only sends auth credentials to its configured host.',
                '' === $urlHost ? '(unparseable)' : $urlHost,
                $baseHost,
            ));
        }

        // Port hardening: reject any explicit port that doesn't match the scheme default.
        $port = parse_url($url, \PHP_URL_PORT);
        $scheme = strtolower(self::parseStringPart($url, \PHP_URL_SCHEME));
        $defaultPort = 'https' === $scheme ? 443 : ('http' === $scheme ? 80 : null);
        if (null !== $port && $port !== $defaultPort) {
            throw new InvalidUrlException(sprintf(
                'Refusing to send a request to non-default port %d on the Quickpay host.',
                $port,
            ));
        }
    }

    private function userAgent(): string
    {
        return 'Setono-Quickpay-PHP (+https://github.com/Setono/quickpay-php-sdk)';
    }

    private static function camelToSnake(string $key): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', $key));
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws MalformedResponseException if the body is not valid JSON or does not decode to an array
     */
    private static function decodeJson(RequestInterface $request, ResponseInterface $response): array
    {
        // 204 No Content has no body by definition (e.g. `DELETE /payments/{id}/link`).
        if (204 === $response->getStatusCode()) {
            return [];
        }

        $body = (string) $response->getBody();

        // Strip query + fragment so consumer-supplied secrets don't land in exception messages.
        $sanitizedUri = $request->getUri()->withQuery('')->withFragment('');
        $context = sprintf(' [%s %s]', $request->getMethod(), (string) $sanitizedUri);

        try {
            $decoded = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MalformedResponseException(
                $response,
                sprintf('Could not decode response body as JSON%s: %s.', $context, $e->getMessage()),
                $e,
                body: $body,
                request: $request,
            );
        }

        if (!is_array($decoded)) {
            throw new MalformedResponseException(
                $response,
                sprintf('Expected decoded response body to be an array but got %s%s.', get_debug_type($decoded), $context),
                body: $body,
                request: $request,
            );
        }

        return $decoded;
    }

    /**
     * `parse_url` for a single string-typed component. Returns `''` for the `null` / `false` cases.
     */
    private static function parseStringPart(string $url, int $component): string
    {
        $value = parse_url($url, $component);

        return is_string($value) ? $value : '';
    }

    private static function assertStatusCode(RequestInterface $request, ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        // Pre-read the body ONCE so the exception's getters work even on non-seekable streams.
        $body = (string) $response->getBody();

        throw match ($statusCode) {
            400, 422 => new ValidationException($response, body: $body, request: $request),
            401 => new UnauthorizedException($response, body: $body, request: $request),
            402, 403 => new ForbiddenException($response, body: $body, request: $request),
            404 => new NotFoundException($response, body: $body, request: $request),
            405 => new MethodNotAllowedException($response, body: $body, request: $request),
            409 => new ConflictException($response, body: $body, request: $request),
            429 => new TooManyRequestsException($response, body: $body, request: $request),
            default => $statusCode >= 500
                ? new InternalServerErrorException($response, body: $body, request: $request)
                : new UnexpectedStatusCodeException($response, body: $body, request: $request),
        };
    }
}
