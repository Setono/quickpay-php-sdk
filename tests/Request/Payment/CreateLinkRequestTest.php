<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateLinkRequestTest extends TestCase
{
    #[Test]
    public function it_passes_a_payment_methods_string_through(): void
    {
        self::assertSame('creditcard,!amex,mobilepay', (new CreateLinkRequest(amount: 1000, paymentMethods: 'creditcard,!amex,mobilepay'))->paymentMethods);
    }

    #[Test]
    public function it_joins_a_payment_methods_list_the_way_quickpay_expects(): void
    {
        // A `(string)` cast of a list would send the literal "Array" — an allowlist naming one unknown
        // method, which rejects every payment with nothing in the config that looks wrong.
        self::assertSame('creditcard,!amex,mobilepay', (new CreateLinkRequest(amount: 1000, paymentMethods: ['creditcard', '!amex', 'mobilepay']))->paymentMethods);
        self::assertSame('visa', (new CreateLinkRequest(amount: 1000, paymentMethods: ['visa']))->paymentMethods);
    }

    #[Test]
    public function an_empty_list_or_null_means_not_set(): void
    {
        self::assertNull((new CreateLinkRequest(amount: 1000, paymentMethods: []))->paymentMethods);
        self::assertNull((new CreateLinkRequest(amount: 1000))->paymentMethods);
        self::assertNull(CreateLinkRequest::joinPaymentMethods(null));
    }

    #[Test]
    public function the_property_stays_a_plain_string_that_can_be_reassigned(): void
    {
        $request = new CreateLinkRequest(amount: 1000, paymentMethods: ['visa']);
        $request->paymentMethods = CreateLinkRequest::joinPaymentMethods(['visa', 'mastercard']);

        self::assertSame('visa,mastercard', $request->paymentMethods);
    }
}
