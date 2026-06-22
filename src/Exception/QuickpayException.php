<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

/**
 * Marker interface implemented by every exception thrown by this SDK.
 *
 * Consumers can write `catch (QuickpayException $e) { ... }` to net all SDK-thrown exceptions
 * without catching `\Throwable` or maintaining an exception list.
 */
interface QuickpayException extends \Throwable
{
}
