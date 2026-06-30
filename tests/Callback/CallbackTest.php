<?php

declare(strict_types=1);

namespace Setono\Quickpay\Callback;

use Nyholm\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use Setono\Quickpay\Enum\PaymentState;
use Setono\Quickpay\Exception\InvalidCallbackException;
use Setono\Quickpay\Exception\InvalidChecksumException;
use Setono\Quickpay\QuickpayTestCase;

final class CallbackTest extends QuickpayTestCase
{
    private const PRIVATE_KEY = 'super-secret-private-key';

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

        $request = new Request(
            'POST',
            'https://shop.example/callback',
            [CallbackValidator::CHECKSUM_HEADER => $checksum],
            $raw,
        );

        self::assertTrue($validator->isValidRequest($request));
    }

    #[Test]
    public function it_deserializes_a_callback_body_into_a_payment(): void
    {
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $payment = $handler->deserialize(self::fixture('callback_payment.json'));

        self::assertSame(9999, $payment->id);
        self::assertSame('cb-1', $payment->orderId);
        self::assertSame(PaymentState::Processed, $payment->state());
        self::assertSame('cb-1', $payment->raw['order_id']);
    }

    #[Test]
    public function it_handles_a_valid_callback_end_to_end(): void
    {
        $raw = self::fixture('callback_payment.json');
        $handler = new CallbackHandler(self::PRIVATE_KEY);
        $checksum = $handler->validator()->sign($raw);

        $payment = $handler->handle($raw, $checksum);

        self::assertSame(9999, $payment->id);
    }

    #[Test]
    public function it_throws_on_an_invalid_checksum(): void
    {
        $handler = new CallbackHandler(self::PRIVATE_KEY);

        $this->expectException(InvalidChecksumException::class);

        $handler->handle(self::fixture('callback_payment.json'), 'not-the-right-checksum');
    }

    #[Test]
    public function it_throws_an_invalid_callback_exception_on_non_json(): void
    {
        $this->expectException(InvalidCallbackException::class);

        (new CallbackHandler(self::PRIVATE_KEY))->deserialize('this is not json');
    }

    #[Test]
    public function it_throws_an_invalid_callback_exception_when_the_body_is_not_an_object(): void
    {
        $this->expectException(InvalidCallbackException::class);

        (new CallbackHandler(self::PRIVATE_KEY))->deserialize('"a json string, not an object"');
    }

    #[Test]
    public function it_throws_an_invalid_callback_exception_when_the_body_is_not_a_payment(): void
    {
        $this->expectException(InvalidCallbackException::class);

        // Valid JSON object, but missing the required payment fields.
        (new CallbackHandler(self::PRIVATE_KEY))->deserialize('{"foo":"bar"}');
    }

    #[Test]
    public function it_handles_a_valid_psr7_request_end_to_end(): void
    {
        $raw = self::fixture('callback_payment.json');
        $handler = new CallbackHandler(self::PRIVATE_KEY);
        $checksum = $handler->validator()->sign($raw);

        $request = new Request(
            'POST',
            'https://shop.example/callback',
            [CallbackValidator::CHECKSUM_HEADER => $checksum],
            $raw,
        );

        $payment = $handler->handleRequest($request);

        self::assertSame(9999, $payment->id);
    }
}
