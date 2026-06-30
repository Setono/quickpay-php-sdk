<?php

declare(strict_types=1);

namespace Setono\Quickpay\Enum;

/**
 * The type of resource a callback (webhook) is about, as told by the `QuickPay-Resource-Type` header.
 *
 * Quickpay fires callbacks for payment and subscription operations. The SDK treats this as the
 * authoritative set: {@see \Setono\Quickpay\Callback\CallbackHandler} requires the header to be one of
 * these values and rejects anything else with an `InvalidCallbackException`. If Quickpay ever adds a
 * new callback resource type (e.g. payouts), add its case here.
 */
enum ResourceType: string
{
    case Payment = 'Payment';
    case Subscription = 'Subscription';
}
