<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * Body for `POST /payments/{id}/authorize`.
 *
 * `amount` is the authorization amount in the payment's currency, expressed in the smallest unit
 * (e.g. cents/øre). `extras` is the API's optional hash of acquirer-specific extra parameters; its
 * keys are passed through verbatim (not converted to snake_case).
 *
 * Note: authorizing directly via the API requires you to handle card data yourself (PCI scope). Most
 * integrations instead authorize through the hosted payment window — see
 * {@see \Setono\Quickpay\Client\Endpoint\PaymentsEndpoint::createLink()}.
 */
final class AuthorizePaymentRequest extends Payload
{
    /**
     * @param array<string, mixed> $extras
     */
    public function __construct(
        public ?int $amount = null,
        public array $extras = [],
    ) {
    }
}
