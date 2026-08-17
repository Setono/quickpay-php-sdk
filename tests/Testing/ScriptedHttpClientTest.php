<?php

declare(strict_types=1);

namespace Setono\Quickpay\Testing;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\Quickpay\Client\Client;
use Setono\Quickpay\Exception\NotFoundException;

final class ScriptedHttpClientTest extends TestCase
{
    #[Test]
    public function it_resolves_relative_uris_against_the_quickpay_host(): void
    {
        $http = (new ScriptedHttpClient())->on('payments/1', '{"a":1}')->on('/ping', '{"message":"Pong"}');

        $response = $http->sendRequest((new Psr17Factory())->createRequest('GET', 'https://api.quickpay.net/payments/1'));

        self::assertSame('{"a":1}', (string) $response->getBody());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"message":"Pong"}', (string) $http->sendRequest((new Psr17Factory())->createRequest('GET', 'https://api.quickpay.net/ping'))->getBody());
    }

    #[Test]
    public function it_prefers_a_method_specific_script_over_a_plain_uri_script(): void
    {
        $http = (new ScriptedHttpClient())
            ->on('payments/1', '{"verb":"any"}')
            ->on('PATCH payments/1', '{"verb":"patch"}', 202, ['X-Test' => 'yes'])
        ;
        $factory = new Psr17Factory();

        self::assertSame('{"verb":"any"}', (string) $http->sendRequest($factory->createRequest('GET', 'https://api.quickpay.net/payments/1'))->getBody());

        $patched = $http->sendRequest($factory->createRequest('PATCH', 'https://api.quickpay.net/payments/1'));
        self::assertSame('{"verb":"patch"}', (string) $patched->getBody());
        self::assertSame(202, $patched->getStatusCode());
        self::assertSame('yes', $patched->getHeaderLine('X-Test'));
    }

    #[Test]
    public function it_accepts_a_ready_made_response(): void
    {
        $response = new Response(418, ['Content-Type' => 'text/plain'], 'teapot');
        $http = (new ScriptedHttpClient())->on('DELETE https://api.quickpay.net/things/1', $response);

        self::assertSame($response, $http->sendRequest((new Psr17Factory())->createRequest('DELETE', 'https://api.quickpay.net/things/1')));
    }

    #[Test]
    public function it_has_no_last_request_before_anything_was_sent(): void
    {
        self::assertNull((new ScriptedHttpClient())->lastRequest());
    }

    #[Test]
    public function it_records_every_request_in_order_and_exposes_the_last_one(): void
    {
        $http = (new ScriptedHttpClient())->on('a', '{}')->on('b', '{}');
        $factory = new Psr17Factory();

        $http->sendRequest($factory->createRequest('GET', 'https://api.quickpay.net/a'));
        $http->sendRequest($factory->createRequest('POST', 'https://api.quickpay.net/b'));

        self::assertCount(2, $http->sentRequests);
        $last = $http->lastRequest();
        self::assertNotNull($last);
        self::assertSame('POST', $last->getMethod());
        self::assertSame('https://api.quickpay.net/b', (string) $last->getUri());
    }

    #[Test]
    public function it_throws_a_logic_exception_listing_the_scripts_when_a_request_is_not_scripted(): void
    {
        $http = (new ScriptedHttpClient())->on('payments/1', '{}');

        try {
            $http->sendRequest((new Psr17Factory())->createRequest('GET', 'https://api.quickpay.net/payments/2'));
            self::fail('Expected a LogicException.');
        } catch (\LogicException $e) {
            self::assertStringContainsString('no script for "GET https://api.quickpay.net/payments/2"', $e->getMessage());
            self::assertStringContainsString('https://api.quickpay.net/payments/1', $e->getMessage());
        }

        // The unscripted request is still recorded, so a test can assert what was attempted.
        self::assertCount(1, $http->sentRequests);
    }

    #[Test]
    public function it_says_so_when_nothing_is_scripted(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('(nothing)');

        (new ScriptedHttpClient())->sendRequest((new Psr17Factory())->createRequest('GET', 'https://api.quickpay.net/ping'));
    }

    #[Test]
    public function it_drives_the_sdk_end_to_end_the_way_a_consumer_test_would(): void
    {
        // The whole point of shipping this fake: a consumer wires it into the real Client and gets
        // the SDK's real request building, mapping and error handling — no SDK classes mocked.
        $http = (new ScriptedHttpClient())
            ->on('payments/1234', '{"id":1234,"merchant_id":1,"order_id":"o-1","accepted":true,"currency":"DKK","state":"new","operations":[]}')
            ->on('payments/999', '{"message":"Not found"}', 404)
        ;
        $client = new Client('test-key', httpClient: $http, requestFactory: new Psr17Factory(), streamFactory: new Psr17Factory());

        $payment = $client->payments()->getById(1234);
        self::assertSame('o-1', $payment->orderId);
        self::assertTrue($payment->accepted);

        try {
            $client->payments()->getById(999);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('Not found', $e->getMessageText());
        }

        self::assertSame('Basic ' . base64_encode(':test-key'), $http->lastRequest()?->getHeaderLine('Authorization'));
    }
}
