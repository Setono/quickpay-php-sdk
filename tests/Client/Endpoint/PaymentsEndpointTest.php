<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client\Endpoint;

use PHPUnit\Framework\Attributes\Test;
use Setono\Quickpay\Enum\PaymentState;
use Setono\Quickpay\QuickpayTestCase;
use Setono\Quickpay\Request\Payment\CaptureRequest;
use Setono\Quickpay\Request\Payment\CreateLinkRequest;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;
use Setono\Quickpay\Request\Payment\RefundRequest;
use Setono\Quickpay\Response\Payment\Payment;
use Setono\Quickpay\TestDouble\ScriptedHttpClient;

final class PaymentsEndpointTest extends QuickpayTestCase
{
    #[Test]
    public function it_gets_a_payment_by_id_and_stamps_raw(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234', self::fixture('payment.json'));

        $payment = $this->client($http)->payments()->getById(1234);

        self::assertSame(1234, $payment->id);
        self::assertSame('order-0001', $payment->orderId);
        self::assertSame('DKK', $payment->currency);
        self::assertSame(PaymentState::Processed, $payment->state());
        self::assertTrue($payment->testMode);
        self::assertSame(1000, $payment->balance);

        // Untyped fields are reachable via the original snake_case keys.
        self::assertSame('order-0001', $payment->raw['order_id']);
        self::assertSame('Example Shop', $payment->raw['text_on_statement']);
    }

    #[Test]
    public function it_maps_nested_link_operations_and_metadata(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234', self::fixture('payment.json'));

        $payment = $this->client($http)->payments()->getById(1234);

        self::assertNotNull($payment->link);
        self::assertSame('https://payment.quickpay.net/payments/01HZX4M8Q9', $payment->link->url);

        self::assertCount(2, $payment->operations);
        self::assertSame('authorize', $payment->operations[0]->type);
        self::assertSame(1000, $payment->operations[0]->amount);
        self::assertSame('20000', $payment->operations[0]->qpStatusCode);

        self::assertNotNull($payment->metadata);
        self::assertSame('visa', $payment->metadata->brand);
        self::assertSame('0000', $payment->metadata->last4);
    }

    #[Test]
    public function it_creates_a_payment_serializing_snake_case_and_stripping_nulls(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments', self::fixture('payment.json'));

        $payment = $this->client($http)->payments()->create(new CreatePaymentRequest(orderId: 'order-0001', currency: 'DKK'));

        self::assertSame(1234, $payment->id);

        $sent = $http->sentRequests[0];
        self::assertSame('POST', $sent->getMethod());
        self::assertSame('application/json', $sent->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('order-0001', $body['order_id']);
        self::assertSame('DKK', $body['currency']);
        self::assertCount(2, $body, 'null/empty optional fields should be stripped from the body');
        self::assertArrayNotHasKey('text_on_statement', $body);
        self::assertArrayNotHasKey('variables', $body);
    }

    #[Test]
    public function it_captures_with_an_amount_body(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/capture', self::fixture('payment.json'));

        $this->client($http)->payments()->capture(1234, new CaptureRequest(1000));

        $sent = $http->sentRequests[0];
        self::assertSame('POST', $sent->getMethod());
        self::assertSame(self::BASE . '/payments/1234/capture', (string) $sent->getUri());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['amount' => 1000], $body);
    }

    #[Test]
    public function it_refunds_with_an_amount_body(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/refund', self::fixture('payment.json'));

        $this->client($http)->payments()->refund(1234, new RefundRequest(250));

        $sent = $http->sentRequests[0];
        self::assertSame(self::BASE . '/payments/1234/refund', (string) $sent->getUri());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['amount' => 250], $body);
    }

    #[Test]
    public function it_cancels_without_a_body(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/cancel', self::fixture('payment.json'));

        $payment = $this->client($http)->payments()->cancel(1234);

        self::assertSame(1234, $payment->id);

        $sent = $http->sentRequests[0];
        self::assertSame('POST', $sent->getMethod());
        self::assertSame(self::BASE . '/payments/1234/cancel', (string) $sent->getUri());
        self::assertSame('', (string) $sent->getBody());
    }

    #[Test]
    public function it_authorizes_without_a_body(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/authorize', self::fixture('payment.json'));

        $this->client($http)->payments()->authorize(1234);

        $sent = $http->sentRequests[0];
        self::assertSame(self::BASE . '/payments/1234/authorize', (string) $sent->getUri());
        self::assertSame('', (string) $sent->getBody());
    }

    #[Test]
    public function it_creates_a_payment_link_and_returns_the_url(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/link', self::fixture('payment_link.json'));

        $link = $this->client($http)->payments()->createLink(1234, new CreateLinkRequest(
            amount: 1000,
            continueUrl: 'https://shop.example/continue',
            cancelUrl: 'https://shop.example/cancel',
            callbackUrl: 'https://shop.example/callback',
        ));

        self::assertSame('https://payment.quickpay.net/payments/01HZX4M8Q9?language=da', $link->url);

        $sent = $http->sentRequests[0];
        self::assertSame('PUT', $sent->getMethod());
        self::assertSame(self::BASE . '/payments/1234/link', (string) $sent->getUri());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(1000, $body['amount']);
        self::assertSame('https://shop.example/continue', $body['continue_url']);
        self::assertSame('https://shop.example/cancel', $body['cancel_url']);
        self::assertSame('https://shop.example/callback', $body['callback_url']);
    }

    #[Test]
    public function it_lists_payments(): void
    {
        $http = (new ScriptedHttpClient())->on(
            self::BASE . '/payments?page=1&page_size=20',
            self::fixture('payments_list.json'),
        );

        $page = $this->client($http)->payments()->getPage();

        self::assertCount(2, $page);
        self::assertSame(1, $page->page);
        self::assertSame(20, $page->pageSize);

        $first = $page->first();
        self::assertInstanceOf(Payment::class, $first);
        self::assertSame(1, $first->id);
    }
}
