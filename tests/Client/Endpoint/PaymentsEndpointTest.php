<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client\Endpoint;

use CuyZ\Valinor\Mapper\MappingError;
use PHPUnit\Framework\Attributes\Test;
use Setono\Quickpay\Enum\PaymentState;
use Setono\Quickpay\Exception\MappingException;
use Setono\Quickpay\QuickpayTestCase;
use Setono\Quickpay\Request\Payment\AuthorizePaymentRequest;
use Setono\Quickpay\Request\Payment\BasketItem;
use Setono\Quickpay\Request\Payment\CaptureRequest;
use Setono\Quickpay\Request\Payment\CreateLinkRequest;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;
use Setono\Quickpay\Request\Payment\PaymentsQuery;
use Setono\Quickpay\Request\Payment\RefundRequest;
use Setono\Quickpay\Request\Payment\UpdatePaymentRequest;
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

        // The result helpers work on the mapped operations (authorize 1000 + capture 1000, both approved).
        self::assertSame(1000, $payment->authorizedAmount());
        self::assertSame(1000, $payment->capturedAmount());
        self::assertSame(0, $payment->refundedAmount());
        self::assertFalse($payment->isCancelled());
        self::assertFalse($payment->hasPendingOperation());
        $latest = $payment->latestOperation();
        self::assertNotNull($latest);
        self::assertSame(2, $latest->id);
        self::assertTrue($latest->isApproved());
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
    public function it_sends_the_extras_hash_with_its_keys_verbatim(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/capture', self::fixture('payment.json'));

        $this->client($http)->payments()->capture(1234, new CaptureRequest(1000, ['vat_amount' => 200]));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $http->sentRequests[0]->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        // `extras` and the keys inside it are passed through as-is (not converted to snake_case).
        self::assertSame(['amount' => 1000, 'extras' => ['vat_amount' => 200]], $body);
    }

    #[Test]
    public function it_cancels_with_an_empty_json_object_body(): void
    {
        // cancel takes no parameters; the API accepts `{}` (verified live) but rejects `[]`.
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/cancel', self::fixture('payment.json'));

        $payment = $this->client($http)->payments()->cancel(1234);

        self::assertSame(1234, $payment->id);

        $sent = $http->sentRequests[0];
        self::assertSame('POST', $sent->getMethod());
        self::assertSame(self::BASE . '/payments/1234/cancel', (string) $sent->getUri());
        self::assertSame('{}', (string) $sent->getBody());
        self::assertSame('application/json', $sent->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function it_authorizes_with_an_amount_body(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/authorize', self::fixture('payment.json'));

        $this->client($http)->payments()->authorize(1234, new AuthorizePaymentRequest(1000));

        $sent = $http->sentRequests[0];
        self::assertSame(self::BASE . '/payments/1234/authorize', (string) $sent->getUri());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['amount' => 1000], $body);
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
    public function it_updates_a_payment_with_the_patch_method(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234', self::fixture('payment.json'));

        $payment = $this->client($http)->payments()->updatePayment(1234, new UpdatePaymentRequest(variables: ['ref' => 'abc']));

        self::assertSame(1234, $payment->id);

        $sent = $http->sentRequests[0];
        self::assertSame('PATCH', $sent->getMethod());
        self::assertSame(self::BASE . '/payments/1234', (string) $sent->getUri());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['variables' => ['ref' => 'abc']], $body);
    }

    #[Test]
    public function it_can_request_a_synchronized_operation(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/capture?synchronized', self::fixture('payment.json'));

        $this->client($http)->payments()->capture(1234, new CaptureRequest(1000), synchronized: true);

        self::assertSame(self::BASE . '/payments/1234/capture?synchronized', (string) $http->sentRequests[0]->getUri());
    }

    #[Test]
    public function it_serializes_basket_items_as_a_list_of_snake_case_objects(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments', self::fixture('payment.json'));

        $request = new CreatePaymentRequest('order-0001', 'DKK');
        $request->basket = [new BasketItem(qty: 2, itemNo: 'sku-1', itemName: 'Widget', itemPrice: 500, vatRate: 0.25)];
        $this->client($http)->payments()->create($request);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $http->sentRequests[0]->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        // The basket must stay a JSON LIST of complete snake_cased objects — the live API rejects
        // partial items and 500s on `[]`-shaped ones.
        self::assertSame(
            [['qty' => 2, 'item_no' => 'sku-1', 'item_name' => 'Widget', 'item_price' => 500, 'vat_rate' => 0.25]],
            $body['basket'],
        );
    }

    #[Test]
    public function it_sends_an_empty_json_object_for_an_empty_payload(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234', self::fixture('payment.json'));

        $this->client($http)->payments()->updatePayment(1234, new UpdatePaymentRequest());

        // An all-unset Payload normalizes to an empty PHP array (`[]` as JSON) — the live API
        // rejects a JSON array body, so the client must send the empty JSON object instead.
        self::assertSame('{}', (string) $http->sentRequests[0]->getBody());
    }

    #[Test]
    public function it_uses_the_client_wide_synchronized_default(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/capture?synchronized', self::fixture('payment.json'));

        $this->client($http, synchronized: true)->payments()->capture(1234, new CaptureRequest(1000));

        self::assertSame(self::BASE . '/payments/1234/capture?synchronized', (string) $http->sentRequests[0]->getUri());
    }

    #[Test]
    public function it_overrides_the_client_wide_synchronized_default_per_call(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/capture', self::fixture('payment.json'));

        $this->client($http, synchronized: true)->payments()->capture(1234, new CaptureRequest(1000), synchronized: false);

        self::assertSame(self::BASE . '/payments/1234/capture', (string) $http->sentRequests[0]->getUri());
    }

    #[Test]
    public function it_throws_a_mapping_exception_when_a_2xx_body_does_not_fit_the_dto(): void
    {
        // Valinor is strict: a single mis-typed field fails the WHOLE resource mapping (the `$raw`
        // fallback only protects fields the SDK does not type). `id` cannot cast to int here.
        $http = (new ScriptedHttpClient())->on(
            self::BASE . '/payments/1234',
            '{"id":"nope","order_id":"o-1","currency":"DKK","state":"new","merchant_id":1}',
        );

        try {
            $this->client($http)->payments()->getById(1234);
            self::fail('Expected a MappingException.');
        } catch (MappingException $e) {
            self::assertStringContainsString('Could not map response body to', $e->getMessage());
            self::assertStringContainsString('[GET https://api.quickpay.net/payments/1234]', $e->getMessage());
            self::assertInstanceOf(MappingError::class, $e->getPrevious());
        }
    }

    #[Test]
    public function it_maps_both_supported_date_formats(): void
    {
        $http = (new ScriptedHttpClient())->on(
            self::BASE . '/payments/1234',
            '{"id":1234,"order_id":"o-1","currency":"DKK","state":"new","merchant_id":1,'
            . '"created_at":"2018-10-17T13:25:44Z","updated_at":"2018-10-17T13:25:44.557Z"}',
        );

        $payment = $this->client($http)->payments()->getById(1234);

        self::assertSame('2018-10-17 13:25:44 +00:00', $payment->createdAt?->format('Y-m-d H:i:s P'));
        self::assertSame('2018-10-17 13:25:44.557000', $payment->updatedAt?->format('Y-m-d H:i:s.u'));
    }

    #[Test]
    public function it_maps_a_202_accepted_operation_response(): void
    {
        // Async operations answer 202 Accepted; the body is still the full payment resource.
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/capture', self::fixture('payment.json'), 202);

        $payment = $this->client($http)->payments()->capture(1234, new CaptureRequest(1000));

        self::assertSame(1234, $payment->id);
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

    #[Test]
    public function it_lists_payments_with_typed_filters(): void
    {
        $http = (new ScriptedHttpClient())->on(
            self::BASE . '/payments?state=new&accepted=true&min_time=2026-08-01%2000%3A00%3A00%20%2B0000&sort_dir=desc&page=2&page_size=5',
            self::fixture('payments_list.json'),
        );

        $page = $this->client($http)->payments()->getPage(new PaymentsQuery(
            state: PaymentState::New,
            accepted: true,
            minTime: new \DateTimeImmutable('2026-08-01 00:00:00', new \DateTimeZone('UTC')),
            sortDir: 'desc',
            page: 2,
            pageSize: 5,
        ));

        self::assertCount(2, $page);
        self::assertSame(2, $page->page);
        self::assertSame(5, $page->pageSize);
    }

    #[Test]
    public function it_finds_a_payment_by_order_id(): void
    {
        $http = (new ScriptedHttpClient())->on(
            self::BASE . '/payments?order_id=o-2&page=1&page_size=1',
            '[{"id":2,"merchant_id":1,"order_id":"o-2","accepted":true,"currency":"DKK","state":"processed","test_mode":true,"operations":[]}]',
        );

        $payment = $this->client($http)->payments()->findByOrderId('o-2');

        self::assertInstanceOf(Payment::class, $payment);
        self::assertSame(2, $payment->id);
        self::assertSame('o-2', $payment->orderId);
        self::assertSame('o-2', $payment->raw['order_id']);
    }

    #[Test]
    public function it_returns_null_when_no_payment_has_the_order_id(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments?order_id=nope&page=1&page_size=1', '[]');

        self::assertNull($this->client($http)->payments()->findByOrderId('nope'));
    }

    #[Test]
    public function it_never_returns_a_payment_for_a_different_order_id(): void
    {
        // The live API matches order_id exactly; should that ever loosen (prefix / case-insensitive
        // matching), findByOrderId() must still only hand back the exact order.
        $http = (new ScriptedHttpClient())->on(
            self::BASE . '/payments?order_id=o-&page=1&page_size=1',
            self::fixture('payments_list.json'),
        );

        self::assertNull($this->client($http)->payments()->findByOrderId('o-'));
    }

    #[Test]
    public function it_deletes_the_payment_link(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments/1234/link', '', 204);

        $this->client($http)->payments()->deleteLink(1234);

        self::assertSame('DELETE', $http->sentRequests[0]->getMethod());
        self::assertSame(self::BASE . '/payments/1234/link', (string) $http->sentRequests[0]->getUri());
    }
}
