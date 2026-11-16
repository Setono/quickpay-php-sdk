<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Thrown when Quickpay's answer started to arrive but its body broke off before it was read in full.
 *
 * A streaming PSR-18 client (Symfony's `Psr18Client`, for one) returns from `sendRequest()` once the
 * headers arrive; a connection that breaks off after that only surfaces when the body is read, as
 * the PSR-7 stream's `\RuntimeException` — available via {@see \Throwable::getPrevious()}.
 *
 * Unlike a {@see TransportException}, the request DID reach Quickpay and the status line and headers
 * did arrive: {@see self::getResponse()} carries them, with an empty body. Check the status code
 * before assuming the request failed — a 2xx answer to an operation (e.g. a cancel or capture) means
 * Quickpay may well have carried it out, so look the payment up before retrying.
 *
 * That is also why it is deliberately NOT a PSR-18 {@see ClientExceptionInterface}: PSR-18 reserves
 * those for a request that could not be sent or got no response at all, and handlers commonly read
 * them as "nothing happened, safe to retry". Catch it as a {@see QuickpayException}.
 *
 * The request is available via {@see self::getRequest()} — its URI is safe to log (the SDK only
 * ever sends to the Quickpay host), but remember it carries the `Authorization` header.
 */
final class IncompleteResponseException extends \RuntimeException implements QuickpayException
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        \RuntimeException $previous,
    ) {
        $uri = $request->getUri()->withQuery('')->withFragment('');

        parent::__construct(
            sprintf(
                'The response body could not be read in full [%s %s] (status code: %d): %s',
                $request->getMethod(),
                (string) $uri,
                $response->getStatusCode(),
                $previous->getMessage(),
            ),
            0,
            $previous,
        );
    }

    /**
     * The request whose answer broke off (with the SDK's headers already applied).
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * The status line and headers that did arrive. The body is empty: it never arrived in full.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
