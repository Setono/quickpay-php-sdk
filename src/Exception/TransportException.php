<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Thrown when a request could not be sent or no response was received at all — a DNS failure,
 * connection refused, TLS error, timeout, or a malformed request rejected by the HTTP client.
 *
 * This wraps the PSR-18 {@see ClientExceptionInterface} thrown by the underlying HTTP client so
 * that `catch (QuickpayException $e)` really nets everything the SDK throws. It still implements
 * `ClientExceptionInterface` itself, so a `catch (ClientExceptionInterface $e)` keeps working; the
 * original exception is available via {@see \Throwable::getPrevious()} (check it for
 * {@see NetworkExceptionInterface} to distinguish network failures from request errors).
 *
 * The request is available via {@see self::getRequest()} — its URI is safe to log (the SDK only
 * ever sends to the Quickpay host), but remember it carries the `Authorization` header.
 */
final class TransportException extends \RuntimeException implements QuickpayException, ClientExceptionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        ClientExceptionInterface $previous,
    ) {
        $uri = $request->getUri()->withQuery('')->withFragment('');

        parent::__construct(
            sprintf(
                'The request could not be sent [%s %s]: %s',
                $request->getMethod(),
                (string) $uri,
                $previous->getMessage(),
            ),
            0,
            $previous,
        );
    }

    /**
     * The request that could not be sent (with the SDK's headers already applied).
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Whether the underlying failure was a network error (DNS, connection, TLS, timeout) as opposed
     * to a request the HTTP client refused to send.
     */
    public function isNetworkError(): bool
    {
        return $this->getPrevious() instanceof NetworkExceptionInterface;
    }
}
