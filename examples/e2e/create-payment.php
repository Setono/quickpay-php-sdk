<?php

declare(strict_types=1);

/**
 * Create a real payment + payment link, then print the window URL to open in a browser.
 *
 *   QUICKPAY_CALLBACK_BASE=https://xxx.sharedwithexpose.com \
 *     php examples/e2e/create-payment.php [amount=1000] [currency=DKK]
 *
 * The callback base URL must point at your running listener through the Expose tunnel.
 */

use Setono\Quickpay\Request\Payment\CreateLinkRequest;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;

require __DIR__ . '/bootstrap.php';

$positional = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $arg): bool => !str_starts_with($arg, '--'),
));

$amount = isset($positional[0]) ? (int) $positional[0] : 1000;
$currency = $positional[1] ?? 'DKK';
$base = e2e_callback_base($argv);

$payments = e2e_client()->payments();

$orderId = 'e2e-' . bin2hex(random_bytes(6));
$payment = $payments->create(new CreatePaymentRequest(orderId: $orderId, currency: $currency));

$link = $payments->createLink($payment->id, new CreateLinkRequest(
    amount: $amount,
    callbackUrl: $base . '/callback',
    continueUrl: $base . '/continue',
    cancelUrl: $base . '/cancel',
));

fwrite(STDOUT, sprintf(
    <<<TXT
        Created payment
          id:        %d
          order_id:  %s
          amount:    %d %s
          callback:  %s/callback

        Open the payment window in a browser:
          %s

        Pay with a test card (any valid-looking expiry + CVD), e.g.:
          1000 0000 0000 0008   approved
          1000 0000 0000 0016   rejected
          1000 0000 0000 0032   capture rejected
          1000 0000 0000 0073   3-D Secure required

        Then watch the listener terminal for the verified callback, and drive the
        rest of the lifecycle with:
          php examples/e2e/operate.php capture %d %d
          php examples/e2e/operate.php refund %d %d
          php examples/e2e/operate.php get %d

        TXT,
    $payment->id,
    $orderId,
    $amount,
    $currency,
    $base,
    $link->url ?? '(no url returned)',
    $payment->id,
    $amount,
    $payment->id,
    $amount,
    $payment->id,
));
