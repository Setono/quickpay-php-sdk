<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client\Endpoint;

use Setono\Quickpay\Request\Payment\AuthorizePaymentRequest;
use Setono\Quickpay\Request\Payment\CaptureRequest;
use Setono\Quickpay\Request\Payment\CreateLinkRequest;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;
use Setono\Quickpay\Request\Payment\RefundRequest;
use Setono\Quickpay\Request\Payment\UpdatePaymentRequest;
use Setono\Quickpay\Response\Payment\Link;
use Setono\Quickpay\Response\Payment\Payment;

/**
 * The `payments` resource: create, read, list and operate on payments.
 *
 * Amounts passed to `authorize` / `capture` / `refund` are integers in the smallest unit of the
 * payment's currency (e.g. `1000` = 10.00 DKK).
 *
 * @extends CollectionEndpoint<Payment>
 */
final class PaymentsEndpoint extends CollectionEndpoint
{
    /**
     * GET `/payments/{id}`.
     */
    public function getById(int $id): Payment
    {
        return $this->getOne($id);
    }

    /**
     * POST `/payments`.
     */
    public function create(CreatePaymentRequest $request): Payment
    {
        return $this->createOne($request);
    }

    /**
     * PUT `/payments/{id}`.
     */
    public function updatePayment(int $id, UpdatePaymentRequest $request): Payment
    {
        return $this->update($id, $request);
    }

    /**
     * POST `/payments/{id}/authorize`. The body is required — the live API validates `amount` as
     * required, so a request-less authorize can never succeed. Pass `$synchronized = true` to wait for and return the
     * completed transaction instead of the default asynchronous (pending) response; `null` (the
     * default) falls back to the client-wide `synchronized` flag set on the `Client` constructor.
     */
    public function authorize(int $id, AuthorizePaymentRequest $request, ?bool $synchronized = null): Payment
    {
        return $this->operation($id, 'authorize', $request, $synchronized);
    }

    /**
     * POST `/payments/{id}/capture`. Pass `$synchronized = true` to wait for and return the completed
     * transaction instead of the default asynchronous (pending) response; `null` (the default) falls
     * back to the client-wide `synchronized` flag set on the `Client` constructor.
     */
    public function capture(int $id, CaptureRequest $request, ?bool $synchronized = null): Payment
    {
        return $this->operation($id, 'capture', $request, $synchronized);
    }

    /**
     * POST `/payments/{id}/refund`. Pass `$synchronized = true` to wait for and return the completed
     * transaction instead of the default asynchronous (pending) response; `null` (the default) falls
     * back to the client-wide `synchronized` flag set on the `Client` constructor.
     */
    public function refund(int $id, RefundRequest $request, ?bool $synchronized = null): Payment
    {
        return $this->operation($id, 'refund', $request, $synchronized);
    }

    /**
     * POST `/payments/{id}/cancel`. Pass `$synchronized = true` to wait for and return the completed
     * transaction instead of the default asynchronous (pending) response; `null` (the default) falls
     * back to the client-wide `synchronized` flag set on the `Client` constructor.
     */
    public function cancel(int $id, ?bool $synchronized = null): Payment
    {
        return $this->operation($id, 'cancel', null, $synchronized);
    }

    /**
     * PUT `/payments/{id}/link` — create or update the payment window link. Redirect the customer to
     * the returned {@see Link::$url}.
     */
    public function createLink(int $id, CreateLinkRequest $request): Link
    {
        return $this->mapItem(Link::class, $this->putSub($id, 'link', $request));
    }

    protected static function getPath(): string
    {
        return 'payments';
    }

    protected static function getItemClass(): string
    {
        return Payment::class;
    }
}
