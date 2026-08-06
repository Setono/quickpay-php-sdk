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
use Setono\Quickpay\Exception\ResponseAwareException;
use Setono\Quickpay\Exception\TooManyRequestsException;
use Setono\Quickpay\Exception\UnauthorizedException;
use Setono\Quickpay\Exception\UnexpectedStatusCodeException;
use Setono\Quickpay\Exception\ValidationException;
use Setono\Quickpay\QuickpayTestCase;
use Setono\Quickpay\Request\CollectionRequestOptions;
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
}
