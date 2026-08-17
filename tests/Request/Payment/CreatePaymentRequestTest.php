<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\Quickpay\Exception\InvalidArgumentException;

final class CreatePaymentRequestTest extends TestCase
{
    #[Test]
    #[DataProvider('validOrderIds')]
    public function it_accepts_order_ids_the_api_accepts(string $orderId): void
    {
        self::assertSame($orderId, (new CreatePaymentRequest(orderId: $orderId, currency: 'DKK'))->orderId);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validOrderIds(): iterable
    {
        // All accepted by the live API (2026-08-17).
        yield '4 chars' => ['ab12'];
        yield '20 chars' => [str_repeat('z', 20)];
        yield 'upper case' => ['AB-CD716b'];
        yield 'space' => ['ab cd2a64'];
        yield 'dot' => ['ab.cd2a64'];
        yield 'underscore' => ['ab_cd2a64'];
        yield 'dash' => ['ab-cd2a64'];
        yield 'typical' => ['order-0001'];
    }

    #[Test]
    #[DataProvider('invalidOrderIds')]
    public function it_rejects_order_ids_the_api_rejects(string $orderId, string $expectedMessagePart): void
    {
        try {
            new CreatePaymentRequest(orderId: $orderId, currency: 'DKK');
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('letters, digits, space, ".", "_" and "-"', $e->getMessage());
            self::assertStringContainsString($expectedMessagePart, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidOrderIds(): iterable
    {
        // All rejected by the live API (2026-08-17) — every one of them under the message
        // "must have length between 4 and 20", which is why the SDK names the actual rule.
        yield 'too short' => ['abc', 'got 3 character(s)'];
        yield 'too long' => [str_repeat('z', 21), 'got 21 character(s)'];
        yield 'empty' => ['', 'got 0 character(s)'];
        yield 'slash' => ['ab/cd2a64', 'outside that set'];
        yield 'hash' => ['ab#cd2a64', 'outside that set'];
        yield 'colon' => ['ab:cd2a64', 'outside that set'];
        yield 'at' => ['ab@cd2a64', 'outside that set'];
        yield 'plus' => ['ab+cd2a64', 'outside that set'];
        yield 'percent' => ['ab%cd2a64', 'outside that set'];
        yield 'comma' => ['ab,cd2a64', 'outside that set'];
        yield 'tab' => ["ab\tcd2a64", 'outside that set'];
        yield 'non-ascii, 20 chars' => [str_repeat('a', 15) . 'ø2a64', 'got 20 character(s), including characters outside that set'];
        yield 'non-ascii, 7 chars' => ['abcdø64', 'outside that set'];
    }

    #[Test]
    public function the_pattern_is_public_so_consumers_can_pre_validate(): void
    {
        self::assertSame(1, preg_match(CreatePaymentRequest::ORDER_ID_PATTERN, 'order-0001'));
        self::assertSame(0, preg_match(CreatePaymentRequest::ORDER_ID_PATTERN, 'order/0001'));

        $this->expectException(InvalidArgumentException::class);
        CreatePaymentRequest::assertValidOrderId('no');
    }
}
