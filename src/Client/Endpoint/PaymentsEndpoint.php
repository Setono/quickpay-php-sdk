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
     * POST `/payments/{id}/authorize`.
     */
    public function authorize(int $id, ?AuthorizePaymentRequest $request = null): Payment
    {
        return $this->operation($id, 'authorize', $request);
    }

    /**
     * POST `/payments/{id}/capture`.
     */
    public function capture(int $id, CaptureRequest $request): Payment
    {
        return $this->operation($id, 'capture', $request);
    }

    /**
     * POST `/payments/{id}/refund`.
     */
    public function refund(int $id, RefundRequest $request): Payment
    {
        return $this->operation($id, 'refund', $request);
    }

    /**
     * POST `/payments/{id}/cancel`.
     */
    public function cancel(int $id): Payment
    {
        return $this->operation($id, 'cancel');
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
