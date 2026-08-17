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

    #[Test]
    public function it_defaults_the_boolean_flags_to_false(): void
    {
        $payment = new Payment(id: 1, orderId: 'o', currency: 'DKK', state: 'new', merchantId: 1);

        self::assertFalse($payment->accepted);
        self::assertFalse($payment->testMode);
        self::assertFalse((new Operation(id: 1, type: 'capture'))->pending);
    }

    #[Test]
    public function operation_is_approved_only_when_completed_with_status_20000(): void
    {
        self::assertTrue((new Operation(id: 1, type: 'capture', pending: false, qpStatusCode: '20000'))->isApproved());
        self::assertFalse((new Operation(id: 1, type: 'capture', pending: true, qpStatusCode: '20000'))->isApproved(), 'a pending operation is not approved yet — even if a stale code is present');
        self::assertFalse((new Operation(id: 1, type: 'capture', pending: false, qpStatusCode: '40000'))->isApproved(), 'rejected by acquirer');
        self::assertFalse((new Operation(id: 1, type: 'capture', pending: false, qpStatusCode: '30100'))->isApproved(), '3-D Secure required is not approved');
        self::assertFalse((new Operation(id: 1, type: 'capture', pending: false, qpStatusCode: null))->isApproved());
        self::assertTrue((new Operation(id: 1, type: 'capture', qpStatusCode: Operation::QP_STATUS_APPROVED))->isApproved());
    }

    #[Test]
    public function operation_matches_its_type_by_enum_or_string(): void
    {
        $operation = new Operation(id: 1, type: 'capture');

        self::assertTrue($operation->isOfType(OperationType::Capture));
        self::assertTrue($operation->isOfType('capture'));
        self::assertFalse($operation->isOfType(OperationType::Refund));
        self::assertFalse($operation->isOfType('Capture'));
    }

    #[Test]
    public function it_finds_operations_by_id_and_type_and_the_latest_one(): void
    {
        $payment = self::payment(
            $authorize = self::operation(1, 'authorize', 1000),
            $capture = self::operation(2, 'capture', 400),
            $capture2 = self::operation(3, 'capture', 600),
        );

        self::assertSame($capture, $payment->operation(2));
        self::assertNull($payment->operation(99));
        self::assertSame([$capture, $capture2], $payment->operationsOfType(OperationType::Capture));
        self::assertSame([$authorize], $payment->operationsOfType('authorize'));
        self::assertSame([], $payment->operationsOfType(OperationType::Refund));
        self::assertSame($capture2, $payment->latestOperation());
    }

    #[Test]
    public function the_latest_operation_is_the_highest_id_regardless_of_order(): void
    {
        $payment = self::payment(
            self::operation(2, 'capture', 400),
            $latest = self::operation(3, 'refund', 100),
            self::operation(1, 'authorize', 1000),
        );

        self::assertSame($latest, $payment->latestOperation());
        self::assertNull(self::payment()->latestOperation());
    }

    #[Test]
    public function it_sums_only_approved_operations_into_the_amounts(): void
    {
        $payment = self::payment(
            self::operation(1, 'authorize', 1000),
            self::operation(2, 'capture', 400),
            self::operation(3, 'capture', 300, qpStatusCode: '40000'), // rejected — must not count
            self::operation(4, 'capture', 200, pending: true), // still pending — must not count
            self::operation(5, 'capture', 100),
            self::operation(6, 'refund', 50),
            self::operation(7, 'refund', 25),
        );

        self::assertSame(1000, $payment->authorizedAmount());
        self::assertSame(500, $payment->capturedAmount());
        self::assertSame(75, $payment->refundedAmount());
        self::assertTrue($payment->hasPendingOperation());
        self::assertFalse($payment->isCancelled());
    }

    #[Test]
    public function it_counts_a_recurring_operation_as_an_authorization(): void
    {
        $payment = self::payment(self::operation(1, 'recurring', 900));

        self::assertSame(900, $payment->authorizedAmount());
    }

    #[Test]
    public function it_treats_a_missing_operation_amount_as_zero(): void
    {
        $payment = self::payment(new Operation(id: 1, type: 'capture', amount: null, pending: false, qpStatusCode: '20000'));

        self::assertSame(0, $payment->capturedAmount());
    }

    #[Test]
    public function it_is_cancelled_only_with_an_approved_cancel_operation(): void
    {
        self::assertTrue(self::payment(self::operation(1, 'authorize', 1000), self::operation(2, 'cancel', null))->isCancelled());
        self::assertFalse(self::payment(self::operation(1, 'authorize', 1000), self::operation(2, 'cancel', null, pending: true))->isCancelled());
        self::assertFalse(self::payment(self::operation(1, 'authorize', 1000), self::operation(2, 'cancel', null, qpStatusCode: '40000'))->isCancelled());
        self::assertFalse(self::payment(self::operation(1, 'authorize', 1000))->isCancelled());
    }

    #[Test]
    public function it_reports_no_pending_operation_when_all_have_completed(): void
    {
        self::assertFalse(self::payment(self::operation(1, 'authorize', 1000), self::operation(2, 'capture', 1000))->hasPendingOperation());
        self::assertFalse(self::payment()->hasPendingOperation());
    }

    #[Test]
    public function the_amount_helpers_are_zero_without_operations(): void
    {
        $payment = self::payment();

        self::assertSame(0, $payment->authorizedAmount());
        self::assertSame(0, $payment->capturedAmount());
        self::assertSame(0, $payment->refundedAmount());
    }

    private static function payment(Operation ...$operations): Payment
    {
        return new Payment(id: 1, orderId: 'o', currency: 'DKK', state: 'processed', merchantId: 1, operations: array_values($operations));
    }

    private static function operation(int $id, string $type, ?int $amount, bool $pending = false, string $qpStatusCode = '20000'): Operation
    {
        return new Operation(id: $id, type: $type, amount: $amount, pending: $pending, qpStatusCode: $qpStatusCode);
    }
}
