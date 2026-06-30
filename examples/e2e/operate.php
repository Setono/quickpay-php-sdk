<?php

declare(strict_types=1);

/**
 * Drive a payment's lifecycle and print the result.
 *
 *   php examples/e2e/operate.php <get|capture|refund|cancel> <paymentId> [amount]
 *
 * capture/refund/cancel run with ?synchronized so the API returns the completed transaction for
 * immediate feedback; the asynchronous callback still lands in the listener.
 */

use Setono\Quickpay\Request\Payment\CaptureRequest;
use Setono\Quickpay\Request\Payment\RefundRequest;
use Setono\Quickpay\Response\Payment\Payment;

require __DIR__ . '/bootstrap.php';

$action = $argv[1] ?? '';
$id = isset($argv[2]) ? (int) $argv[2] : 0;
$amount = isset($argv[3]) ? (int) $argv[3] : null;

if ('' === $action || $id <= 0) {
    e2e_fail('Usage: php examples/e2e/operate.php <get|capture|refund|cancel> <paymentId> [amount]');
}

$payments = e2e_client()->payments();

$payment = match ($action) {
    'get' => $payments->getById($id),
    'capture' => $payments->capture($id, new CaptureRequest(e2e_require_amount($amount)), synchronized: true),
    'refund' => $payments->refund($id, new RefundRequest(e2e_require_amount($amount)), synchronized: true),
    'cancel' => $payments->cancel($id, synchronized: true),
    default => e2e_fail(sprintf('Unknown action "%s". Use one of: get, capture, refund, cancel.', $action)),
};

e2e_print_payment($payment);

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
