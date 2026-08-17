<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client\Endpoint;

use Setono\Quickpay\Client\Client;
use Setono\Quickpay\Request\Payment\AuthorizePaymentRequest;
use Setono\Quickpay\Request\Payment\CaptureRequest;
use Setono\Quickpay\Request\Payment\CreateLinkRequest;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;
use Setono\Quickpay\Request\Payment\PaymentsQuery;
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
 * Listing (`getPage()` / `paginate()`) accepts a {@see PaymentsQuery} to filter by `order_id`,
 * state, acceptance, creation time, etc. — see {@see self::findByOrderId()} for the common
 * "look up the payment for an order" case.
 *
 * The operation methods (`authorize`, `capture`, `refund`, `cancel`) share one contract: Quickpay
 * processes them asynchronously by default and answers `202 Accepted`. The returned {@see Payment}
 * is then only a snapshot taken when the operation was QUEUED — the new operation has
 * `pending: true` and no `qpStatusCode` yet, and fields such as `state` and `balance` still hold
 * their pre-operation values; they say nothing about the outcome. Confirm the result via the
 * callback, or by re-fetching with {@see self::getById()} until the operation's `pending` is
 * `false` (a `qpStatusCode` of `"20000"` then means approved). Pass `$synchronized = true` to make
 * Quickpay wait and return the completed transaction instead; `null` (the default) falls back to
 * the client-wide `synchronized` flag set on the `Client` constructor.
 *
 * They also take a `$callbackUrl`: Quickpay POSTs the callback for an API-issued operation to the
 * ACCOUNT-WIDE callback URL (manager → Settings → Integration) — not to the `callbackUrl` you set on
 * the payment link, and the account-wide one is empty by default. Pass the URL you want notified
 * (typically the same endpoint as the link's) and it is sent as the `QuickPay-Callback-Url` header
 * for that one operation; the resulting operation's `callbackUrl` reflects it. Verified live.
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
     * Find the payment created for the given `order_id`, or `null` if there is none.
     *
     * `GET /payments?order_id={orderId}`. The API matches `order_id` exactly (case-sensitive) and
     * enforces it to be unique per account — a second `create()` with the same `order_id` fails
     * with a `ValidationException` ("already exists on another payment") — so this is the
     * building block for creating payments idempotently: look the order up first, and only
     * `create()` when nothing is found.
     */
    public function findByOrderId(string $orderId): ?Payment
    {
        foreach ($this->getPage(new PaymentsQuery(orderId: $orderId, pageSize: 1)) as $payment) {
            // Defensive: never hand back a different order, whatever the API's matching rules become.
            if ($payment->orderId === $orderId) {
                return $payment;
            }
        }

        return null;
    }

    /**
     * POST `/payments`.
     */
    public function create(CreatePaymentRequest $request): Payment
    {
        return $this->createOne($request);
    }

    /**
     * PATCH `/payments/{id}`.
     */
    public function updatePayment(int $id, UpdatePaymentRequest $request): Payment
    {
        return $this->updateOne($id, $request);
    }

    /**
     * POST `/payments/{id}/authorize`. The body is required — the live API validates `amount` as
     * required, so a request-less authorize can never succeed. Note this puts you in PCI scope
     * (card data); most integrations authorize through the payment window ({@see self::createLink()}).
     * Async by default — see the class docblock for what the response does (and doesn't) tell you
     * and for `$synchronized` / `$callbackUrl`.
     */
    public function authorize(int $id, AuthorizePaymentRequest $request, ?bool $synchronized = null, ?string $callbackUrl = null): Payment
    {
        return $this->postOperationWithHeaders($id, 'authorize', $request, $synchronized, [Client::CALLBACK_URL_HEADER => $callbackUrl]);
    }

    /**
     * POST `/payments/{id}/capture` — capture (part of) an authorized amount; several partial
     * captures are possible. Async by default — see the class docblock for what the response does
     * (and doesn't) tell you and for `$synchronized` / `$callbackUrl`.
     */
    public function capture(int $id, CaptureRequest $request, ?bool $synchronized = null, ?string $callbackUrl = null): Payment
    {
        return $this->postOperationWithHeaders($id, 'capture', $request, $synchronized, [Client::CALLBACK_URL_HEADER => $callbackUrl]);
    }

    /**
     * POST `/payments/{id}/refund` — refund (part of) the captured balance. Async by default — see
     * the class docblock for what the response does (and doesn't) tell you and for `$synchronized` /
     * `$callbackUrl`.
     */
    public function refund(int $id, RefundRequest $request, ?bool $synchronized = null, ?string $callbackUrl = null): Payment
    {
        return $this->postOperationWithHeaders($id, 'refund', $request, $synchronized, [Client::CALLBACK_URL_HEADER => $callbackUrl]);
    }

    /**
     * POST `/payments/{id}/cancel` — void the authorization (no parameters; an empty `{}` body is
     * sent). Async by default — see the class docblock for what the response does (and doesn't)
     * tell you and for `$synchronized` / `$callbackUrl`.
     */
    public function cancel(int $id, ?bool $synchronized = null, ?string $callbackUrl = null): Payment
    {
        return $this->postOperationWithHeaders($id, 'cancel', [], $synchronized, [Client::CALLBACK_URL_HEADER => $callbackUrl]);
    }

    /**
     * PUT `/payments/{id}/link` — create or update the payment window link. Redirect the customer to
     * the returned {@see Link::$url}.
     */
    public function createLink(int $id, CreateLinkRequest $request): Link
    {
        return $this->mapItem(Link::class, $this->putSubResource($id, 'link', $request));
    }

    /**
     * DELETE `/payments/{id}/link` — invalidate the payment window link so the customer can no
     * longer pay through it (e.g. when the order is cancelled before payment).
     */
    public function deleteLink(int $id): void
    {
        $this->deleteSubResource($id, 'link');
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
