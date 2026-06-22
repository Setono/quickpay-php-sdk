<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Payment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\Quickpay\Enum\OperationType;
use Setono\Quickpay\Enum\PaymentState;

final class PaymentTest extends TestCase
{
    #[Test]
    public function it_resolves_a_known_state_to_the_enum(): void
    {
        $payment = new Payment(id: 1, orderId: 'o', currency: 'DKK', state: 'processed', merchantId: 1);

        self::assertSame(PaymentState::Processed, $payment->state());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_state(): void
    {
        $payment = new Payment(id: 1, orderId: 'o', currency: 'DKK', state: 'a_state_quickpay_added_later', merchantId: 1);

        self::assertNull($payment->state());
    }

    #[Test]
    public function operation_resolves_a_known_type_to_the_enum(): void
    {
        self::assertSame(OperationType::Capture, (new Operation(id: 1, type: 'capture'))->type());
    }

    #[Test]
    public function operation_returns_null_for_an_unknown_type(): void
    {
        self::assertNull((new Operation(id: 1, type: 'brand_new_type'))->type());
    }
}
