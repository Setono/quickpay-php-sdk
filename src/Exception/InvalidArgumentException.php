<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Thrown when a request object is built with a value the Quickpay API is known to reject — e.g. an
 * `order_id` outside 4–20 characters, or a page number below 1 — so the mistake fails fast at the
 * call site with a message naming the rule, instead of after a network round-trip (sometimes with
 * an API message that blames the wrong thing).
 *
 * Extends the SPL {@see \InvalidArgumentException} so existing catch sites keep working, and
 * implements {@see QuickpayException} so `catch (QuickpayException $e)` nets it too.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements QuickpayException
{
}
