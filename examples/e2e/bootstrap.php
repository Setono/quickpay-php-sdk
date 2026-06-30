<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the end-to-end harness. Loads the autoloader and exposes a few helpers used by
 * the listener and CLI scripts. NOT part of the library — this is dev tooling under examples/.
 */

use Setono\Quickpay\Callback\CallbackHandler;
use Setono\Quickpay\Client\Client;

require __DIR__ . '/../../vendor/autoload.php';

e2e_load_dotenv();

/**
 * Load KEY=VALUE pairs from a `.env.local` file at the repo root into the environment (without
 * overriding variables already set for real). Lets you keep secrets in one local, gitignored file
 * instead of exporting them every time. The function is hoisted, so calling it above is fine.
 */
function e2e_load_dotenv(): void
{
    $path = __DIR__ . '/../../.env.local';
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
    if (false === $lines) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ('' === $line || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (strlen($value) >= 2
            && (('"' === $value[0] && '"' === $value[-1]) || ("'" === $value[0] && "'" === $value[-1]))) {
            $value = substr($value, 1, -1);
        }

        // Never override a variable already set in the real environment.
        if ('' === $name || false !== getenv($name)) {
            continue;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

/**
 * Read an environment variable. When required and missing, print guidance and exit.
 */
function e2e_env(string $name, bool $required = true): string
{
    $value = getenv($name);
    if (!is_string($value) || '' === $value) {
        if ($required) {
            fwrite(STDERR, sprintf("Missing required environment variable: %s\n\n", $name));
            fwrite(STDERR, "The harness needs:\n");
            fwrite(STDERR, "  QUICKPAY_API_KEY      your API key       (Quickpay manager > Settings > API user)\n");
            fwrite(STDERR, "  QUICKPAY_PRIVATE_KEY  your private key   (Quickpay manager > Settings > Integration)\n");
            fwrite(STDERR, "Export them in your shell before running the harness. Never commit them.\n");
            exit(1);
        }

        return '';
    }

    return $value;
}

function e2e_client(): Client
{
    // Only the API key is required; the PSR-18 client + PSR-17 factories are auto-discovered
    // (buzz + nyholm are installed as dev dependencies).
    return new Client(e2e_env('QUICKPAY_API_KEY'));
}

function e2e_callback_handler(): CallbackHandler
{
    // The private key is the account private key, NOT the API key.
    return new CallbackHandler(e2e_env('QUICKPAY_PRIVATE_KEY'));
}

function e2e_log_path(): string
{
    $dir = __DIR__ . '/var';
    if (!is_dir($dir)) {
        mkdir($dir, 0o775, true);
    }

    return $dir . '/callbacks.log';
}

/**
 * Log a line to the terminal and append it to var/callbacks.log (shown on the listener's index page).
 */
function e2e_log(string $line): void
{
    $stamped = sprintf('[%s] %s', date('Y-m-d H:i:s'), $line);

    if (defined('STDERR')) {
        fwrite(STDERR, $stamped . "\n");
    } else {
        // The built-in server SAPI (cli-server) does not define STDERR; error_log reaches the terminal.
        error_log($stamped);
    }

    @file_put_contents(e2e_log_path(), $stamped . "\n", FILE_APPEND);
}

/**
 * Resolve the public callback base URL from `--callback-base=` or the QUICKPAY_CALLBACK_BASE env var.
 * Must be an https URL (Quickpay requires TLS for callbacks). Exits with guidance when missing/invalid.
 *
 * @param list<string> $argv
 */
function e2e_callback_base(array $argv): string
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

    if ('' === $base) {
        fwrite(STDERR, "Missing the public callback base URL.\n");
        fwrite(STDERR, "Pass --callback-base=https://xxx.sharedwithexpose.com or set QUICKPAY_CALLBACK_BASE.\n");
        fwrite(STDERR, "This is the https URL printed by `expose share` (it changes every session on the free tier).\n");
        exit(1);
    }

    $base = rtrim($base, '/');

    if (!str_starts_with($base, 'https://')) {
        fwrite(STDERR, sprintf("The callback base URL must be https:// (Quickpay requires TLS). Got: %s\n", $base));
        exit(1);
    }

    return $base;
}

/**
 * @return never
 */
function e2e_fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
