<?php

declare(strict_types=1);

/**
 * Quick REAL-API smoke test: ping → create a payment → create a payment link → fetch it back.
 *
 *   php examples/e2e/smoke.php [amount=1000] [currency=DKK]
 *
 * Hits the live Quickpay API using QUICKPAY_API_KEY (from .env.local or the environment). It charges
 * nothing — no card is entered — and uses placeholder continue/cancel URLs since it doesn't open the
 * payment window. Each step runs independently and reports its own result, so a restricted API key
 * (e.g. the default "Payment Window" user, which can't GET /ping) still shows what it CAN do.
 */

use Setono\Quickpay\Exception\QuickpayException;
use Setono\Quickpay\Request\Payment\CreateLinkRequest;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;

require __DIR__ . '/bootstrap.php';

$amount = isset($argv[1]) ? (int) $argv[1] : 1000;
$currency = $argv[2] ?? 'DKK';

$payments = e2e_client()->payments();

$report = static function (string $label, string $result): void {
    fwrite(STDOUT, sprintf("%-8s ... %s\n", $label, $result));
};

// ping
try {
    $report('ping', e2e_client()->ping() ? 'ok' : 'unexpected response');
} catch (QuickpayException $e) {
    $report('ping', 'FAILED: ' . $e->getMessage());
}

// create
$paymentId = null;
try {
    $payment = $payments->create(new CreatePaymentRequest(
        orderId: 'smoke-' . bin2hex(random_bytes(6)),
        currency: $currency,
    ));
    $paymentId = $payment->id;
    $report('create', sprintf('ok id=%d state=%s test_mode=%s', $payment->id, $payment->state, $payment->testMode ? 'true' : 'false'));
} catch (QuickpayException $e) {
    $report('create', 'FAILED: ' . $e->getMessage());
}

if (null !== $paymentId) {
    // link
    try {
        $link = $payments->createLink($paymentId, new CreateLinkRequest(
            amount: $amount,
            continueUrl: 'https://example.com/continue',
            cancelUrl: 'https://example.com/cancel',
        ));
        $report('link', 'ok ' . ($link->url ?? '(no url returned)'));
    } catch (QuickpayException $e) {
        $report('link', 'FAILED: ' . $e->getMessage());
    }

    // get
    try {
        $fetched = $payments->getById($paymentId);
        $report('get', sprintf('ok id=%d order_id=%s state=%s', $fetched->id, $fetched->orderId, $fetched->state));
    } catch (QuickpayException $e) {
        $report('get', 'FAILED: ' . $e->getMessage());
    }
}
