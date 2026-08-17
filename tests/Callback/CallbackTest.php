<?php

declare(strict_types=1);

namespace Setono\Quickpay\Callback;

use CuyZ\Valinor\Cache\FileSystemCache;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use Setono\Quickpay\Enum\PaymentState;
use Setono\Quickpay\Enum\ResourceType;
use Setono\Quickpay\Exception\InvalidCallbackException;
use Setono\Quickpay\Exception\InvalidChecksumException;
use Setono\Quickpay\QuickpayTestCase;

final class CallbackTest extends QuickpayTestCase
{
    private const PRIVATE_KEY = 'super-secret-private-key';

    // --- CallbackValidator ---

    #[Test]
    public function it_accepts_a_valid_checksum(): void
    {
        $raw = self::fixture('callback_payment.json');
        $validator = new CallbackValidator(self::PRIVATE_KEY);

        $checksum = hash_hmac('sha256', $raw, self::PRIVATE_KEY);

        self::assertSame($checksum, $validator->sign($raw));
        self::assertTrue($validator->isValid($raw, $checksum));
    }

    #[Test]
    public function it_rejects_a_tampered_body(): void
    {
        $raw = self::fixture('callback_payment.json');
        $validator = new CallbackValidator(self::PRIVATE_KEY);
        $checksum = $validator->sign($raw);

        self::assertFalse($validator->isValid($raw . ' ', $checksum));
    }

    #[Test]
    public function it_rejects_a_checksum_signed_with_a_different_key(): void
    {
        $raw = self::fixture('callback_payment.json');
        $checksum = (new CallbackValidator('another-key'))->sign($raw);

        self::assertFalse((new CallbackValidator(self::PRIVATE_KEY))->isValid($raw, $checksum));
    }

    #[Test]
    public function it_validates_a_psr7_request(): void
    {
        $raw = self::fixture('callback_payment.json');
        $validator = new CallbackValidator(self::PRIVATE_KEY);
        $checksum = $validator->sign($raw);

        $request = new ServerRequest(
            'POST',
            'https://shop.example/callback',
            [CallbackValidator::CHECKSUM_HEADER => $checksum],
            $raw,
        );

        self::assertTrue($validator->isValidRequest($request));
    }

    // --- CallbackHandler / Callback ---

    #[Test]
    public function it_returns_a_verified_payment_callback(): void
    {
        $raw = self::fixture('callback_payment.json');
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $callback = $handler->handleRaw($raw, $handler->validator()->sign($raw), ResourceType::Payment->value);

        self::assertSame(ResourceType::Payment, $callback->type);
        self::assertTrue($callback->isPayment());

        $payment = $callback->payment();
        self::assertSame(9999, $payment->id);
        self::assertSame('cb-1', $payment->orderId);
        self::assertSame(PaymentState::Processed, $payment->state());
        self::assertSame('cb-1', $payment->raw['order_id']);
    }

    #[Test]
    public function it_reads_all_quickpay_headers_off_a_psr7_request(): void
    {
        $raw = self::fixture('callback_payment.json');
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $request = new ServerRequest('POST', 'https://shop.example/callback', [
            CallbackValidator::CHECKSUM_HEADER => $handler->validator()->sign($raw),
            Callback::RESOURCE_TYPE_HEADER => 'Payment',
            Callback::ACCOUNT_ID_HEADER => '12345',
            Callback::API_VERSION_HEADER => 'v10',
        ], $raw);

        $callback = $handler->handle($request);

        self::assertSame(ResourceType::Payment, $callback->type);
        self::assertTrue($callback->isPayment());
        self::assertSame('12345', $callback->accountId);
        self::assertSame('v10', $callback->apiVersion);
        self::assertSame(9999, $callback->payment()->id);
    }

    #[Test]
    public function it_throws_on_an_invalid_checksum(): void
    {
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $this->expectException(InvalidChecksumException::class);

        $handler->handleRaw(self::fixture('callback_payment.json'), 'not-the-right-checksum', ResourceType::Payment->value);
    }

    #[Test]
    public function it_throws_on_an_invalid_checksum_from_a_request(): void
    {
        $raw = self::fixture('callback_payment.json');
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $request = new ServerRequest('POST', 'https://shop.example/callback', [
            CallbackValidator::CHECKSUM_HEADER => 'not-the-right-checksum',
            Callback::RESOURCE_TYPE_HEADER => 'Payment',
        ], $raw);

        $this->expectException(InvalidChecksumException::class);

        $handler->handle($request);
    }

    #[Test]
    public function it_throws_on_an_unexpected_resource_type(): void
    {
        $raw = self::fixture('callback_payment.json');
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $this->expectException(InvalidCallbackException::class);

        // Valid checksum, but a resource type the SDK doesn't model.
        $handler->handleRaw($raw, $handler->validator()->sign($raw), 'Payout');
    }

    #[Test]
    public function it_throws_on_a_missing_resource_type(): void
    {
        $raw = self::fixture('callback_payment.json');
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $request = new ServerRequest(
            'POST',
            'https://shop.example/callback',
            [CallbackValidator::CHECKSUM_HEADER => $handler->validator()->sign($raw)],
            $raw,
        );

        $this->expectException(InvalidCallbackException::class);

        $handler->handle($request);
    }

    #[Test]
    public function it_does_not_map_a_non_payment_resource_to_a_payment(): void
    {
        $raw = '{"id":42,"state":"active"}';
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $callback = $handler->handleRaw($raw, $handler->validator()->sign($raw), ResourceType::Subscription->value);

        self::assertSame(ResourceType::Subscription, $callback->type);
        self::assertFalse($callback->isPayment());
        self::assertSame(['id' => 42, 'state' => 'active'], $callback->toArray());

        // Assert the MESSAGE too: without it, removing the type guard would still end in an
        // InvalidCallbackException via the shape-mismatch path and the guard would go untested.
        $this->expectException(InvalidCallbackException::class);
        $this->expectExceptionMessage('not a payment');
        $callback->payment();
    }

    #[Test]
    public function payment_throws_an_invalid_callback_exception_on_non_json(): void
    {
        $handler = new CallbackHandler(self::PRIVATE_KEY);
        $callback = $handler->handleRaw('this is not json', $handler->validator()->sign('this is not json'), ResourceType::Payment->value);

        $this->expectException(InvalidCallbackException::class);
        $callback->payment();
    }

    #[Test]
    public function payment_throws_when_the_body_is_not_an_object(): void
    {
        $handler = new CallbackHandler(self::PRIVATE_KEY);
        $body = '"a json string, not an object"';
        $callback = $handler->handleRaw($body, $handler->validator()->sign($body), ResourceType::Payment->value);

        $this->expectException(InvalidCallbackException::class);
        $callback->payment();
    }

    #[Test]
    public function payment_throws_when_the_body_is_not_a_payment(): void
    {
        $handler = new CallbackHandler(self::PRIVATE_KEY);
        $body = '{"foo":"bar"}';
        $callback = $handler->handleRaw($body, $handler->validator()->sign($body), ResourceType::Payment->value);

        $this->expectException(InvalidCallbackException::class);
        $callback->payment();
    }

    #[Test]
    public function the_handler_uses_the_given_cache_for_its_default_mapper(): void
    {
        $dir = self::tempDir();
        $handler = new CallbackHandler(self::PRIVATE_KEY, cache: new FileSystemCache($dir));
        $raw = self::fixture('callback_payment.json');

        $payment = $handler->handleRaw($raw, $handler->validator()->sign($raw), ResourceType::Payment->value)->payment();

        self::assertSame(9999, $payment->id);
        self::assertDirectoryExists($dir, 'the cache directory should have been populated');
        self::removeDir($dir);
    }
}
