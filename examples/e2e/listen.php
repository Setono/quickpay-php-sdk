<?php

declare(strict_types=1);

/**
 * Callback listener for the end-to-end harness.
 *
 * Run it as the router for PHP's built-in server, bound to 0.0.0.0 so the Expose container can reach it:
 *
 *   php -S 0.0.0.0:8000 examples/e2e/listen.php
 *
 * Routes:
 *   POST /callback   verify + deserialize a Quickpay callback, then log a summary
 *   GET  /continue   landing page after a successful payment (browser redirect target)
 *   GET  /cancel     landing page after a cancelled payment
 *   GET  /           recent verified callbacks (tail of var/callbacks.log)
 */

use Setono\Quickpay\Exception\InvalidCallbackException;
use Setono\Quickpay\Exception\InvalidChecksumException;

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), \PHP_URL_PATH) ?: '/';

if ('POST' === $method && '/callback' === $path) {
    $rawBody = (string) file_get_contents('php://input');
    $checksum = (string) ($_SERVER['HTTP_QUICKPAY_CHECKSUM_SHA256'] ?? '');

    try {
        // handle() verifies the checksum against the raw body, then deserializes to a Payment.
        $payment = e2e_callback_handler()->handle($rawBody, $checksum);
    } catch (InvalidChecksumException $e) {
        http_response_code(403);
        e2e_log('CALLBACK REJECTED (bad checksum): ' . $e->getMessage());
        echo "invalid checksum\n";

        return;
    } catch (InvalidCallbackException $e) {
        http_response_code(400);
        e2e_log('CALLBACK INVALID (bad body): ' . $e->getMessage());
        echo "invalid callback body\n";

        return;
    }

    $operations = $payment->operations;
    $lastOp = [] === $operations ? null : $operations[array_key_last($operations)];

    e2e_log(sprintf(
        'CALLBACK OK  payment=%d order=%s test_mode=%s accepted=%s state=%s%s',
        $payment->id,
        $payment->orderId,
        $payment->testMode ? 'true' : 'false',
        $payment->accepted ? 'true' : 'false',
        $payment->state,
        null === $lastOp ? '' : sprintf(
            ' | last_op=%s qp=%s aq=%s',
            $lastOp->type,
            $lastOp->qpStatusCode ?? '-',
            $lastOp->aqStatusCode ?? '-',
        ),
    ));

    http_response_code(200);
    echo "ok\n";

    return;
}

if ('GET' === $method && '/continue' === $path) {
    echo e2e_page('Payment completed', 'Your test payment completed. Check the listener terminal (or the index page) for the verified callback.');

    return;
}

if ('GET' === $method && '/cancel' === $path) {
    echo e2e_page('Payment cancelled', 'The test payment was cancelled.');

    return;
}

if ('GET' === $method && '/' === $path) {
    $log = is_file(e2e_log_path()) ? (string) file_get_contents(e2e_log_path()) : '';
    $recent = implode("\n", array_slice(array_values(array_filter(explode("\n", $log))), -50));

    header('Content-Type: text/plain; charset=utf-8');
    echo "Quickpay e2e callback listener\n\nRecent callbacks (newest last):\n\n" . ('' === $recent ? '(none yet)' : $recent) . "\n";

    return;
}

http_response_code(404);
echo "not found\n";

function e2e_page(string $title, string $body): string
{
    return sprintf(
        '<!doctype html><meta charset="utf-8"><title>%s</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:40rem;margin:4rem auto;line-height:1.5">'
        . '<h1>%s</h1><p>%s</p></body>',
        htmlspecialchars($title, \ENT_QUOTES),
        htmlspecialchars($title, \ENT_QUOTES),
        htmlspecialchars($body, \ENT_QUOTES),
    );
}
