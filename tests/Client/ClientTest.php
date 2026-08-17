<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\Quickpay\Exception\ConflictException;
use Setono\Quickpay\Exception\ForbiddenException;
use Setono\Quickpay\Exception\InternalServerErrorException;
use Setono\Quickpay\Exception\InvalidUrlException;
use Setono\Quickpay\Exception\MalformedResponseException;
use Setono\Quickpay\Exception\MethodNotAllowedException;
use Setono\Quickpay\Exception\NotFoundException;
use Setono\Quickpay\Exception\QuickpayException;
use Setono\Quickpay\Exception\ResponseAwareException;
use Setono\Quickpay\Exception\TooManyRequestsException;
use Setono\Quickpay\Exception\TransportException;
use Setono\Quickpay\Exception\UnauthorizedException;
use Setono\Quickpay\Exception\UnexpectedStatusCodeException;
use Setono\Quickpay\Exception\ValidationException;
use Setono\Quickpay\QuickpayTestCase;
use Setono\Quickpay\Request\CollectionRequestOptions;
use Setono\Quickpay\Request\Payment\Shipping;
use Setono\Quickpay\TestDouble\ScriptedHttpClient;

final class ClientTest extends QuickpayTestCase
{
    #[Test]
    public function it_sends_basic_auth_with_empty_user_and_required_headers(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));

        $this->client($http)->ping();

        $request = $http->sentRequests[0];
        self::assertSame('Basic ' . base64_encode(':' . self::API_KEY), $request->getHeaderLine('Authorization'));
        self::assertSame('v10', $request->getHeaderLine('Accept-Version'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertStringStartsWith('Setono-Quickpay-PHP', $request->getHeaderLine('User-Agent'));
    }

    #[Test]
    public function it_uses_the_quickpay_api_host(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));

        $this->client($http)->ping();

        $uri = $http->sentRequests[0]->getUri();
        self::assertSame('api.quickpay.net', $uri->getHost());
        self::assertSame('https', $uri->getScheme());
        self::assertSame(self::BASE . '/ping', (string) $uri);
    }

    #[Test]
    public function it_builds_the_pagination_query_string(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments?page=2&page_size=25', '[]');

        $this->client($http)->payments()->getPage(new CollectionRequestOptions(2, 25));

        self::assertSame(self::BASE . '/payments?page=2&page_size=25', (string) $http->sentRequests[0]->getUri());
    }

    #[Test]
    public function it_pings(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));

        self::assertTrue($this->client($http)->ping());
    }

    #[Test]
    public function it_is_not_synchronized_by_default(): void
    {
        self::assertFalse($this->client(new ScriptedHttpClient())->isSynchronized());
    }

    #[Test]
    public function it_exposes_the_synchronized_flag_given_to_the_constructor(): void
    {
        self::assertTrue($this->client(new ScriptedHttpClient(), synchronized: true)->isSynchronized());
    }

    #[Test]
    public function it_has_no_last_request_or_response_before_dispatching(): void
    {
        $client = $this->client(new ScriptedHttpClient());

        self::assertNull($client->getLastRequest());
        self::assertNull($client->getLastResponse());
    }

    #[Test]
    public function it_records_the_last_request_and_response(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));
        $client = $this->client($http);

        $client->ping();

        $request = $client->getLastRequest();
        $response = $client->getLastResponse();
        self::assertNotNull($request);
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    #[Test]
    #[DataProvider('statusCodeProvider')]
    public function it_maps_status_codes_to_typed_exceptions(int $status, string $expected): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('error_validation.json'), $status);

        try {
            $this->client($http)->ping();
            self::fail('Expected an exception to be thrown.');
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);
        }
    }

    /**
     * @return iterable<string, array{int, class-string<\Throwable>}>
     */
    public static function statusCodeProvider(): iterable
    {
        yield '400' => [400, ValidationException::class];
        yield '401' => [401, UnauthorizedException::class];
        yield '402' => [402, ForbiddenException::class];
        yield '403' => [403, ForbiddenException::class];
        yield '404' => [404, NotFoundException::class];
        yield '405' => [405, MethodNotAllowedException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, ValidationException::class];
        yield '429' => [429, TooManyRequestsException::class];
        yield '500' => [500, InternalServerErrorException::class];
        yield '503' => [503, InternalServerErrorException::class];
        yield '418' => [418, UnexpectedStatusCodeException::class];
        yield '302' => [302, UnexpectedStatusCodeException::class];
    }

    #[Test]
    public function it_throws_when_the_body_is_valid_json_but_not_an_array(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', '"pong"');

        $this->expectException(MalformedResponseException::class);
        $this->expectExceptionMessage('Expected decoded response body to be an array but got string');

        $this->client($http)->ping();
    }

    #[Test]
    public function it_does_not_set_a_content_type_on_get_requests(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));

        $this->client($http)->ping();

        self::assertFalse($http->sentRequests[0]->hasHeader('Content-Type'));
    }

    #[Test]
    public function it_preserves_a_preset_content_type(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));

        $request = (new Psr17Factory())->createRequest('POST', self::BASE . '/ping')
            ->withHeader('Content-Type', 'application/custom+json');
        $this->client($http)->request($request);

        self::assertSame('application/custom+json', $http->sentRequests[0]->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function it_allows_an_absolute_url_regardless_of_casing(): void
    {
        // RFC 3986 hosts are case-insensitive — the host-pinning guard must not reject the
        // Quickpay host just because it is written in upper case, and the port guard must resolve
        // the default port from the lowercased SCHEME too. (The PSR-7 implementation then
        // normalizes scheme + host to lower case and drops the default port on the wire.)
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));

        $this->client($http)->get('HTTPS://API.QUICKPAY.NET:443/ping');

        self::assertSame(self::BASE . '/ping', (string) $http->sentRequests[0]->getUri());
    }

    #[Test]
    public function it_allows_an_explicit_default_port_on_the_api_host(): void
    {
        // An explicit :443 matches the https default, so the port guard must not reject it. (The
        // PSR-7 implementation then drops the redundant default port on the wire.)
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));

        $this->client($http)->get(self::BASE . ':443/ping');

        self::assertSame(self::BASE . '/ping', (string) $http->sentRequests[0]->getUri());
    }

    #[Test]
    public function it_exposes_the_quickpay_error_fields_on_exceptions(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('error_validation.json'), 422);

        try {
            $this->client($http)->ping();
            self::fail('Expected an exception to be thrown.');
        } catch (ResponseAwareException $e) {
            self::assertSame('Validation error', $e->getMessageText());
            self::assertSame('invalid_parameters', $e->getErrorCode());
            self::assertSame(['order_id' => ['has already been taken']], $e->getValidationErrors());
        }
    }

    #[Test]
    public function it_throws_on_a_non_json_body(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', 'definitely not json');

        $this->expectException(MalformedResponseException::class);

        $this->client($http)->ping();
    }

    #[Test]
    public function it_memoizes_the_payments_endpoint(): void
    {
        $client = $this->client(new ScriptedHttpClient());

        self::assertSame($client->payments(), $client->payments());
    }

    #[Test]
    public function it_allows_an_absolute_url_on_the_api_host(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));

        self::assertSame(['message' => 'Pong'], $this->client($http)->get('https://api.quickpay.net/ping'));
    }

    #[Test]
    public function it_refuses_to_send_to_a_foreign_host(): void
    {
        $this->expectException(InvalidUrlException::class);

        $this->client(new ScriptedHttpClient())->get('https://evil.example/payments');
    }

    #[Test]
    public function it_refuses_a_non_default_port_on_the_api_host(): void
    {
        $this->expectException(InvalidUrlException::class);

        $this->client(new ScriptedHttpClient())->get('https://api.quickpay.net:8443/ping');
    }

    #[Test]
    public function it_refuses_an_absolute_url_combined_with_a_query(): void
    {
        $this->expectException(InvalidUrlException::class);

        $this->client(new ScriptedHttpClient())->get('https://api.quickpay.net/ping', ['foo' => 'bar']);
    }

    #[Test]
    public function it_refuses_a_consumer_built_request_to_a_foreign_host(): void
    {
        // request() is the low-level entry point that stamps the API key on a caller-supplied
        // PSR-7 request — it must run the same host-pinning guard as get()/post()/…, otherwise a
        // consumer could (accidentally) ship the credentials to any host with no error at all.
        $http = new ScriptedHttpClient();
        $request = (new Psr17Factory())->createRequest('GET', 'https://evil.example/payments');

        try {
            $this->client($http)->request($request);
            self::fail('Expected an InvalidUrlException.');
        } catch (InvalidUrlException) {
            // The request must be rejected BEFORE anything reaches the transport.
            self::assertSame([], $http->sentRequests);
        }
    }

    #[Test]
    public function it_refuses_a_consumer_built_request_to_a_non_default_port(): void
    {
        $this->expectException(InvalidUrlException::class);

        $request = (new Psr17Factory())->createRequest('GET', 'https://api.quickpay.net:8443/ping');

        $this->client(new ScriptedHttpClient())->request($request);
    }

    #[Test]
    public function it_sends_a_consumer_built_request_to_the_api_host(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/ping', self::fixture('ping.json'));
        $request = (new Psr17Factory())->createRequest('GET', 'HTTPS://API.QUICKPAY.NET/ping');

        $response = $this->client($http)->request($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Basic ' . base64_encode(':' . self::API_KEY), $http->sentRequests[0]->getHeaderLine('Authorization'));
    }

    #[Test]
    public function it_deletes_and_returns_an_empty_array_for_204_no_content(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/link', '', 204);

        self::assertSame([], $this->client($http)->delete('payments/1234/link'));

        $request = $http->sentRequests[0];
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(self::BASE . '/payments/1234/link', (string) $request->getUri());
        self::assertSame('Basic ' . base64_encode(':' . self::API_KEY), $request->getHeaderLine('Authorization'));
        self::assertFalse($request->hasHeader('Content-Type'));
    }

    #[Test]
    public function it_decodes_a_delete_response_body_when_there_is_one(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/things/1', '{"deleted":true}');

        self::assertSame(['deleted' => true], $this->client($http)->delete('things/1'));
    }

    #[Test]
    public function it_treats_204_no_content_as_an_empty_body_on_every_verb(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/things', '', 204);

        self::assertSame([], $this->client($http)->post('things', ['a' => 1]));
    }

    #[Test]
    public function it_still_rejects_an_empty_2xx_body_that_is_not_a_204(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/things', '', 200);

        $this->expectException(MalformedResponseException::class);

        $this->client($http)->post('things', ['a' => 1]);
    }

    #[Test]
    public function it_sends_a_plain_array_body_verbatim(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/subscriptions', '{"id":1}');

        $this->client($http)->post('subscriptions', [
            'order_id' => 'sub-0001',
            'currency' => 'DKK',
            'description' => 'Monthly plan',
            'camelCaseKey' => null, // arrays are NOT snake_cased or null-stripped — they go out as given
            'variables' => ['plan' => 'gold'],
        ]);

        $request = $http->sentRequests[0];
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            '{"order_id":"sub-0001","currency":"DKK","description":"Monthly plan","camelCaseKey":null,"variables":{"plan":"gold"}}',
            (string) $request->getBody(),
        );
    }

    #[Test]
    public function it_transforms_payloads_and_dates_nested_inside_an_array_body(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/things', '{"id":1}');

        $this->client($http)->put('things', [
            'shipping' => new Shipping(trackingNumber: 'TN-1'), // Payload → snake_case + null-stripped
            'deadline_at' => new \DateTimeImmutable('2026-08-17T10:00:00+00:00'),
        ]);

        self::assertSame(
            '{"shipping":{"tracking_number":"TN-1"},"deadline_at":"2026-08-17T10:00:00+00:00"}',
            (string) $http->sentRequests[0]->getBody(),
        );
    }

    #[Test]
    public function it_sends_an_empty_json_object_for_an_empty_array_body(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/things', '{"id":1}');

        $this->client($http)->patch('things', []);

        self::assertSame('{}', (string) $http->sentRequests[0]->getBody());
    }

    #[Test]
    public function it_refuses_to_delete_on_a_foreign_host(): void
    {
        $this->expectException(InvalidUrlException::class);

        $this->client(new ScriptedHttpClient())->delete('https://evil.example/payments/1/link');
    }

    #[Test]
    public function it_wraps_a_psr18_network_failure_in_a_transport_exception(): void
    {
        $psr17 = new Psr17Factory();
        $client = new Client(self::API_KEY, httpClient: new ThrowingHttpClient(network: true), requestFactory: $psr17, streamFactory: $psr17);

        try {
            $client->ping();
            self::fail('Expected a TransportException.');
        } catch (TransportException $e) {
            // (It is a QuickpayException AND a PSR-18 ClientExceptionInterface — see ExceptionHierarchyTest.)
            self::assertInstanceOf(\Psr\Http\Client\NetworkExceptionInterface::class, $e->getPrevious());
            self::assertTrue($e->isNetworkError());
            self::assertSame('The request could not be sent [GET https://api.quickpay.net/ping]: connection refused', $e->getMessage());
            self::assertSame(self::BASE . '/ping', (string) $e->getRequest()->getUri());
            self::assertSame('v10', $e->getRequest()->getHeaderLine('Accept-Version'), 'the request carries the SDK headers');
        }

        // The attempt is recorded, and there is no response to show for it.
        self::assertNotNull($client->getLastRequest());
        self::assertNull($client->getLastResponse());
    }

    #[Test]
    public function it_wraps_a_psr18_request_error_in_a_transport_exception(): void
    {
        $psr17 = new Psr17Factory();
        $client = new Client(self::API_KEY, httpClient: new ThrowingHttpClient(network: false), requestFactory: $psr17, streamFactory: $psr17);

        try {
            $client->ping();
            self::fail('Expected a TransportException.');
        } catch (TransportException $e) {
            self::assertFalse($e->isNetworkError());
            self::assertInstanceOf(\Psr\Http\Client\RequestExceptionInterface::class, $e->getPrevious());
        }
    }

    #[Test]
    public function a_transport_failure_strips_the_query_string_from_the_message(): void
    {
        $psr17 = new Psr17Factory();
        $client = new Client(self::API_KEY, httpClient: new ThrowingHttpClient(network: true), requestFactory: $psr17, streamFactory: $psr17);

        try {
            $client->get('payments', ['order_id' => 'secret-ref']);
            self::fail('Expected a TransportException.');
        } catch (TransportException $e) {
            self::assertStringNotContainsString('secret-ref', $e->getMessage());
            self::assertStringContainsString('[GET https://api.quickpay.net/payments]', $e->getMessage());
        }
    }

    #[Test]
    public function catching_quickpay_exception_nets_a_transport_failure_too(): void
    {
        $psr17 = new Psr17Factory();
        $client = new Client(self::API_KEY, httpClient: new ThrowingHttpClient(network: true), requestFactory: $psr17, streamFactory: $psr17);

        $this->expectException(QuickpayException::class);

        $client->ping();
    }
}

/**
 * A PSR-18 client that always fails at the transport level, the way a real one does on DNS /
 * connection / timeout errors (network) or a request it refuses to send.
 */
final class ThrowingHttpClient implements \Psr\Http\Client\ClientInterface
{
    public function __construct(private readonly bool $network)
    {
    }

    public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        throw $this->network
            ? new class($request) extends \RuntimeException implements \Psr\Http\Client\NetworkExceptionInterface {
                public function __construct(private readonly \Psr\Http\Message\RequestInterface $request)
                {
                    parent::__construct('connection refused');
                }

                public function getRequest(): \Psr\Http\Message\RequestInterface
                {
                    return $this->request;
                }
            }
        : new class($request) extends \RuntimeException implements \Psr\Http\Client\RequestExceptionInterface {
            public function __construct(private readonly \Psr\Http\Message\RequestInterface $request)
            {
                parent::__construct('malformed request');
            }

            public function getRequest(): \Psr\Http\Message\RequestInterface
            {
                return $this->request;
            }
        };
    }
}
