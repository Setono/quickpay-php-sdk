<?php

declare(strict_types=1);

/**
 * Drive a payment's lifecycle and print the result.
 *
 *   php examples/e2e/operate.php <get|capture|refund|cancel> <paymentId> [amount]
 *
 * capture/refund/cancel run with ?synchronized so the API returns the completed transaction for
 * immediate feedback. Their callbacks go to the ACCOUNT-WIDE callback url unless told otherwise, so
 * when QUICKPAY_CALLBACK_BASE is set (or --callback-base= given) the listener's /callback is passed
 * as the per-operation callback url and the asynchronous callback lands there.
 */

use Setono\Quickpay\Request\Payment\CaptureRequest;
use Setono\Quickpay\Request\Payment\RefundRequest;
use Setono\Quickpay\Response\Payment\Payment;

require __DIR__ . '/bootstrap.php';

$positional = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $arg): bool => !str_starts_with($arg, '--'),
));

$action = $positional[0] ?? '';
$id = isset($positional[1]) ? (int) $positional[1] : 0;
$amount = isset($positional[2]) ? (int) $positional[2] : null;
$callbackUrl = e2e_optional_callback_url($argv);

if ('' === $action || $id <= 0) {
    e2e_fail('Usage: php examples/e2e/operate.php <get|capture|refund|cancel> <paymentId> [amount]');
}

$payments = e2e_client()->payments();

$payment = match ($action) {
    'get' => $payments->getById($id),
    'capture' => $payments->capture($id, new CaptureRequest(e2e_require_amount($amount)), synchronized: true, callbackUrl: $callbackUrl),
    'refund' => $payments->refund($id, new RefundRequest(e2e_require_amount($amount)), synchronized: true, callbackUrl: $callbackUrl),
    'cancel' => $payments->cancel($id, synchronized: true, callbackUrl: $callbackUrl),
    default => e2e_fail(sprintf('Unknown action "%s". Use one of: get, capture, refund, cancel.', $action)),
};

e2e_print_payment($payment);

/**
 * The listener's /callback url when a callback base is configured, else null (account-wide default).
 *
 * @param list<string> $argv
 */
function e2e_optional_callback_url(array $argv): ?string
{
    $base = '';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--callback-base=')) {
            $base = substr($arg, strlen('--callback-base='));
        }
    }
    if ('' === $base) {
        $base = e2e_env('QUICKPAY_CALLBACK_BASE', false);
    }

    return '' === $base ? null : rtrim($base, '/') . '/callback';
}

function e2e_require_amount(?int $amount): int
{
    if (null === $amount || $amount <= 0) {
        e2e_fail('This action requires a positive integer amount (smallest currency unit), e.g. 1000.');
    }

    return $amount;
}

function e2e_print_payment(Payment $payment): void
{
    $lines = [
        sprintf('id:        %d', $payment->id),
        sprintf('order_id:  %s', $payment->orderId),
        sprintf('currency:  %s', $payment->currency),
        sprintf('state:     %s', $payment->state),
        sprintf('accepted:  %s', $payment->accepted ? 'true' : 'false'),
        sprintf('test_mode: %s', $payment->testMode ? 'true' : 'false'),
        sprintf('balance:   %s', null === $payment->balance ? '-' : (string) $payment->balance),
        'operations:',
    ];

    foreach ($payment->operations as $op) {
        $lines[] = sprintf(
            '  - %-9s amount=%s pending=%s qp=%s aq=%s',
            $op->type,
            null === $op->amount ? '-' : (string) $op->amount,
            $op->pending ? 'true' : 'false',
            $op->qpStatusCode ?? '-',
            $op->aqStatusCode ?? '-',
        );
    }

    if ([] === $payment->operations) {
        $lines[] = '  (none)';
    }

    fwrite(STDOUT, implode("\n", $lines) . "\n");
}
