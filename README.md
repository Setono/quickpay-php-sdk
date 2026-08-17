# Quickpay PHP SDK

[![Latest Stable Version](https://poser.pugx.org/setono/quickpay-php-sdk/v/stable)](https://packagist.org/packages/setono/quickpay-php-sdk)
[![License](https://poser.pugx.org/setono/quickpay-php-sdk/license)](https://packagist.org/packages/setono/quickpay-php-sdk)
[![Build Status](https://github.com/Setono/quickpay-php-sdk/actions/workflows/build.yaml/badge.svg)](https://github.com/Setono/quickpay-php-sdk/actions)
[![Code Coverage](https://codecov.io/gh/Setono/quickpay-php-sdk/branch/1.x/graph/badge.svg)](https://codecov.io/gh/Setono/quickpay-php-sdk)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fquickpay-php-sdk%2F1.x)](https://dashboard.stryker-mutator.io/reports/github.com/Setono/quickpay-php-sdk/1.x)

Consume the [Quickpay API](https://learn.quickpay.net/tech-talk/api/) in PHP. A small, strongly-typed
SDK focused on the `payments` resource, the `/ping` health check, the payment-window link flow, and
callback (webhook) verification.

Built on PSR-18 (HTTP client), PSR-17 (factories) and PSR-7 (messages), discovered automatically via
[`php-http/discovery`](https://github.com/php-http/discovery), so it works with any compliant HTTP
client.

- [Installation](#installation)
- [Concepts — five things to know about Quickpay](#concepts--five-things-to-know-about-quickpay)
- [Usage](#usage)
  - [Payment link flow](#payment-link-flow-redirect-the-customer-to-the-payment-window) ·
    [Capturing, refunding, cancelling](#capturing-refunding-cancelling) ·
    [Reading what happened to a payment](#reading-what-happened-to-a-payment) ·
    [Updating a payment](#updating-a-payment) ·
    [Reading and listing payments](#reading-and-listing-payments)
  - [Callbacks](#callbacks) — verifying, framework snippets, [handling them robustly](#handling-callbacks-robustly), testing your endpoint
  - [Accessing fields the SDK doesn't model](#accessing-fields-the-sdk-doesnt-model) ·
    [Calling endpoints the SDK doesn't model](#calling-endpoints-the-sdk-doesnt-model) ·
    [Error handling](#error-handling)
- [Recipes](#recipes) — checkout end to end, testing your integration, wiring in Symfony
- [Production usage](#production-usage) · [Contributing](#contributing) · [End-to-end testing](#end-to-end-testing)

## Installation

```bash
composer require setono/quickpay-php-sdk
```

The SDK needs a PSR-18 HTTP client and PSR-17 factories, found at runtime via `php-http/discovery`.
If your project has none yet, Composer's `php-http/discovery` plugin offers to install one for you
during `composer require` (it picks e.g. `symfony/http-client` + `nyholm/psr7`). If you have
disabled that plugin (`allow-plugins`), or prefer to choose, require an implementation yourself:

```bash
composer require symfony/http-client nyholm/psr7   # or guzzlehttp/guzzle, kriswallsmith/buzz, ...
```

Without one, `new Client(...)` throws `Http\Discovery\Exception\NotFoundException` ("No PSR-18
clients found") the first time it runs — the install itself succeeds, so make sure a client is
present before you deploy.

## Concepts — five things to know about Quickpay

1. **Two keys.** The **API key** (manager → Settings → API user) authenticates API calls (`Client`).
   The **private key** (Settings → Integration) signs callbacks (`CallbackHandler`). They are not
   interchangeable, and neither is a "test" key: there is no sandbox. A payment is a *test* payment
   (`test_mode: true`) purely because it was paid with a
   [test card](https://learn.quickpay.net/tech-talk/appendixes/test/); test callbacks are real and
   signed exactly like production.
2. **Amounts are integers in the smallest currency unit** — `1000` is 10.00 DKK / EUR / …, on
   requests and responses alike. The SDK does no currency math.
3. **A payment is a ledger of operations.** `POST /payments` creates an empty payment (state
   `initial`). Everything that happens afterwards — authorize, capture, refund, cancel — is an
   *operation* appended to `payment.operations`, each with `pending` (still being processed) and a
   `qp_status_code` (`"20000"` = approved; `3xxxx` = 3-D Secure / SCA needed, `4xxxx` = rejected or
   invalid, `5xxxx` = gateway/acquirer error — see [Errors and codes](https://learn.quickpay.net/tech-talk/appendixes/errors/)).
   The payment's own fields summarize the ledger: `accepted` (an authorization was approved by the
   acquirer), `state` (`initial` → `pending` while an authorization is in flight → `new` once
   authorized, or `rejected`; `processed` after capture/cancel/refund activity — and `pending` again
   for the moment *any* asynchronous operation is in flight, with `balance` still showing its
   pre-operation value), and `balance` (captured minus refunded). Read the operations, not just the
   state — the SDK's [helpers](#reading-what-happened-to-a-payment) do that for you. A declined
   operation is not retried by Quickpay: e.g. a declined auto-capture leaves the payment `new` /
   authorized, and it is up to you to capture again.
4. **Operations are asynchronous by default.** `capture()` etc. return `202 Accepted` with the new
   operation still `pending: true`; the outcome arrives via the callback (or by re-fetching). Pass
   `synchronized: true` (per call or as a client default) to wait for the result instead — and note
   that a *declined* synchronized operation is still a `2xx`: the decline is on the operation
   (`latestOperationOfType(...)?->isDeclined()`), not an exception.
5. **The redirect back to your shop proves nothing.** When the customer returns to `continue_url`
   the request carries no payment data and can arrive *before* the callback. Treat the signed
   [callback](#callbacks) — or a `getById()` on your side — as the source of truth for "paid".

## Usage

Authenticate with your Quickpay **API key** (Quickpay manager → Settings → API user). The SDK uses
the key as the HTTP Basic password with an empty username, exactly as Quickpay expects. There is no
separate sandbox host or test key — a payment becomes a *test* payment (`test_mode: true`) when it's
paid with a [test card](https://learn.quickpay.net/tech-talk/appendixes/test/).

```php
use Setono\Quickpay\Client\Client;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;

$client = new Client('YOUR_API_KEY');

// Health check
$client->ping(); // true, or throws on a non-2xx response

// Create a payment
$payment = $client->payments()->create(new CreatePaymentRequest(
    orderId: 'order-0001',
    currency: 'DKK',
));

echo $payment->id;        // 1234
echo $payment->state;     // "initial"
echo $payment->state()?->name; // PaymentState enum (or null for an unknown value)
```

Fields the Quickpay API unconditionally requires (verified against the live API) are required
constructor arguments — `orderId` and `currency` here, `amount` on links and operations. Every other
field is optional and simply omitted from the request JSON when unset. The one format rule the SDK
enforces locally is `orderId`: 4–20 characters of letters, digits, space, `.`, `_` and `-`
(`CreatePaymentRequest::ORDER_ID_PATTERN`) — Quickpay rejects anything else, but under a message
that only mentions the length, so the SDK throws a `Setono\Quickpay\Exception\InvalidArgumentException`
naming the actual rule before any request is made.

### Payment link flow (redirect the customer to the payment window)

The recommended way to take a payment is to create the payment, create a link for it, then redirect
the customer to the returned URL. See [the Quickpay docs](https://learn.quickpay.net/tech-talk/payments/link/).

```php
use Setono\Quickpay\Request\Payment\CreateLinkRequest;

$payment = $client->payments()->create(new CreatePaymentRequest(orderId: 'order-0001', currency: 'DKK'));

$link = $client->payments()->createLink($payment->id, new CreateLinkRequest(
    amount: 1000, // 10.00 DKK — amounts are integers in the smallest currency unit
    continueUrl: 'https://shop.example/continue',
    cancelUrl: 'https://shop.example/cancel',
    callbackUrl: 'https://shop.example/callback',
));

header('Location: ' . $link->url);
```

`continueUrl` / `cancelUrl` are where the customer is sent after a successful / cancelled payment;
`callbackUrl` is the server-to-server URL Quickpay POSTs the result to (see [Callbacks](#callbacks)).
To restrict the payment methods the window offers, pass `paymentMethods:` either as Quickpay's
comma-separated string (`'creditcard,!amex,mobilepay'` — `!` excludes) or as a list
(`['creditcard', '!amex', 'mobilepay']`), which the SDK joins for you.
If the order is cancelled before the customer pays, invalidate the link with
`$client->payments()->deleteLink($payment->id)`.

> **The `continueUrl` redirect is not proof of payment.** It carries no data and can arrive before the
> callback. On that page, either wait for the verified callback to have marked the order paid, or
> re-fetch the payment (`getById()`) and check `accepted` / the operations yourself — never mark an
> order paid just because the customer landed there.

### Capturing, refunding, cancelling

```php
use Setono\Quickpay\Request\Payment\CaptureRequest;
use Setono\Quickpay\Request\Payment\RefundRequest;

$client->payments()->capture($payment->id, new CaptureRequest(1000));
$client->payments()->refund($payment->id, new RefundRequest(250));
$client->payments()->cancel($payment->id);
```

Quickpay processes these operations asynchronously by default — the returned payment may still have a
pending operation. Pass `synchronized: true` to wait for and receive the completed transaction:

```php
$payment = $client->payments()->capture($payment->id, new CaptureRequest(1000), synchronized: true);
```

If your integration always (or never) wants to wait, set the default once on the client instead of
repeating the flag on every call — a non-null per-call `synchronized:` argument still overrides it:

```php
$client = new Client('YOUR_API_KEY', synchronized: true);

$client->payments()->capture($payment->id, new CaptureRequest(1000)); // waits (client default)
$client->payments()->refund($payment->id, new RefundRequest(250), synchronized: false); // fire-and-forget
```

**Where does the callback for a capture/refund/cancel go?** Not to the `callbackUrl` you set on the
payment link — Quickpay POSTs the callback of an *API-issued* operation to the **account-wide**
callback URL (manager → Settings → Integration), which is empty by default. So a shop that only ever
set the link's `callbackUrl` never hears about its captures, refunds and cancels. Pass `callbackUrl:`
on the operation and Quickpay notifies that URL for it (sent as the `QuickPay-Callback-Url` header;
verified live — the operation's own `callbackUrl` reflects it and the callback arrives there):

```php
$client->payments()->capture($payment->id, new CaptureRequest(1000), callbackUrl: 'https://shop.example/callback');
$client->payments()->refund($payment->id, new RefundRequest(250), callbackUrl: 'https://shop.example/callback');
$client->payments()->cancel($payment->id, callbackUrl: 'https://shop.example/callback');
```

Typically that's the same endpoint as the link's `callbackUrl` (plus whatever token your framework
needs to route it back to the order). For unmodeled operations use the header directly:
`$client->post('payments/1/renew', [], [Client::CALLBACK_URL_HEADER => $url])`.

### Reading what happened to a payment

Everything that happened to a payment is recorded in its `operations` (authorize, capture, refund,
cancel, …), each with a `pending` flag and Quickpay's `qp_status_code` (`"20000"` = approved).
`Payment` and `Operation` have helpers so you don't have to hand-roll that inspection:

```php
$payment = $client->payments()->getById(1234);

$payment->accepted;            // Quickpay's own flag: the authorization was accepted by the acquirer
$payment->authorizedAmount();  // sum of approved authorize operations (0 if never authorized)
$payment->capturedAmount();    // sum of approved captures
$payment->refundedAmount();    // sum of approved refunds
$payment->isCancelled();       // an approved cancel exists
$payment->hasPendingOperation(); // something is still being processed — don't read the outcome yet

$latest = $payment->latestOperation();  // ?Operation — highest id, i.e. the most recent one
$latest?->isApproved();                 // completed with qp_status_code 20000
$latest?->type();                       // OperationType enum (or null for an unknown value)

$payment->operation(3);                            // ?Operation by id
$payment->operationsOfType(OperationType::Capture); // list<Operation>

// The questions that decide an order's status:
$payment->latestApprovedOperation();                       // newest APPROVED op of any type — where the money actually is;
                                                           // a trailing rejected/pending attempt does not mask it
$payment->latestOperationOfType(OperationType::Capture);   // newest capture, whatever its outcome
$payment->hasApprovedOperation(OperationType::Capture);    // was anything ever captured?
$payment->hasPendingOperation(OperationType::Refund);      // is a refund in flight? (guard before issuing another)

// Reading a single operation's outcome:
$op->hasOutcome();   // no longer pending — the status codes mean something now
$op->isApproved();   // qp_status_code 20000
$op->isDeclined();   // completed but NOT approved: rejected (4xxxx), error (5xxxx), auth required (3xxxx)
```

Only **approved** operations count towards the amounts — pending or rejected ones don't. When an
operation was run asynchronously (the default), poll `getById()` or wait for the callback until
`hasPendingOperation()` is `false` before trusting the amounts. Operation ids are numbered per
payment (`1`, `2`, …), which makes them a good idempotency key when handling callbacks.

A **declined synchronized operation is not an exception**: `capture(..., synchronized: true)` on a
card that declines returns a `2xx` payment whose new operation `isDeclined()`. Check it:

```php
$payment = $client->payments()->capture($id, new CaptureRequest($amount), synchronized: true);
$capture = $payment->latestOperationOfType(OperationType::Capture);
if (null === $capture || !$capture->isApproved()) {
    // declined — $capture?->qpStatusMsg / ->aqStatusMsg say why; Quickpay will not retry it for you
}
```

### Updating a payment

Before a payment is authorized you can update some of its fields (`PATCH /payments/{id}`). Note the
API does not allow changing `order_id` or `basket` after creation:

```php
use Setono\Quickpay\Request\Payment\UpdatePaymentRequest;

$client->payments()->updatePayment($payment->id, new UpdatePaymentRequest(
    variables: ['internal_ref' => 'abc-123'],
));

// Read them back later — keys and value types exactly as you sent them
$client->payments()->getById($payment->id)->variables()['internal_ref']; // "abc-123"
```

If you ship a plugin/module, identify it on the payments it creates with
`new CreatePaymentRequest(..., shopsystem: new Shopsystem(name: 'acme/shop-plugin', version: '2.3.4'))`
— Quickpay shows it on the payment (`metadata.shopsystem_name` / `shopsystem_version`), which helps
telling integrations apart in the manager and when talking to support.

> Authorizing directly via the API — `$client->payments()->authorize($id, new AuthorizePaymentRequest(...))` —
> requires you to handle card data and puts you in PCI scope. Most integrations authorize through the
> payment window instead (see the link flow above).

### Reading and listing payments

```php
$payment = $client->payments()->getById(1234);

// One page
$page = $client->payments()->getPage(); // Collection<Payment>
foreach ($page as $payment) {
    echo $payment->orderId;
}

// All pages (lazily). Quickpay sends no total-count metadata, so pagination stops when a page comes
// back with fewer items than the requested page size.
use Setono\Quickpay\Request\CollectionRequestOptions;

foreach ($client->payments()->paginate(new CollectionRequestOptions(pageSize: 50)) as $payment) {
    // ...
}
```

To filter the list, pass a `PaymentsQuery` instead — it carries the same `page`/`pageSize` plus the
typed filters `GET /payments` supports (`orderId`, `state`, `accepted`, `minTime`/`maxTime`,
`acquirer`, `fraudSuspected`, `id`, `sortBy`/`sortDir`, `operationsSize`; anything else via `extra`):

```php
use Setono\Quickpay\Enum\PaymentState;
use Setono\Quickpay\Request\Payment\PaymentsQuery;

$query = new PaymentsQuery(
    state: PaymentState::New,          // authorized, not yet captured
    accepted: true,
    minTime: new \DateTimeImmutable('-7 days'),
    pageSize: 100,
);

foreach ($client->payments()->paginate($query) as $payment) {
    // ...
}
```

#### Finding the payment for an order (idempotent create)

`order_id` is unique per Quickpay account — creating a second payment with the same `order_id`
fails with a `ValidationException` ("already exists on another payment"). So when a checkout can be
retried (double click, page reload, a crashed request after Quickpay created the payment), look the
order up first and only create when nothing is found:

```php
$payment = $client->payments()->findByOrderId($orderId)
    ?? $client->payments()->create(new CreatePaymentRequest(orderId: $orderId, currency: 'DKK'));
```

`findByOrderId()` matches the `order_id` exactly (case-sensitively) and returns `null` when there is
no such payment.

### Callbacks

Quickpay notifies your `callbackUrl` by POSTing the payment object and signing it with a
`QuickPay-Checksum-Sha256` header — `hash_hmac('sha256', rawBody, privateKey)`. The **private key**
(Quickpay manager → Settings → Integration) is different from the API key.

Always verify the checksum against the **raw, byte-for-byte request body** — do not decode and
re-encode the JSON first, or the checksum won't match. The SDK uses `hash_equals()` for a
timing-safe comparison.

A callback isn't always a payment — Quickpay also sends them for subscriptions — so `handle()`
returns a verified `Callback` carrying the resource type (from the `QuickPay-Resource-Type` header).
That header is required and must be a known value (`Payment` or `Subscription`); an unexpected or
missing one is rejected. Only deserialize to a `Payment` once you know it is one:

```php
use Setono\Quickpay\Callback\CallbackHandler;
use Setono\Quickpay\Enum\ResourceType;

$handler = new CallbackHandler('YOUR_PRIVATE_KEY');

try {
    // $request is your incoming PSR-7 server request (Symfony/Laravel/PSR-15 all give you one).
    // Verifies the checksum and validates the resource type — does NOT assume it's a payment.
    $callback = $handler->handle($request);
} catch (\Setono\Quickpay\Exception\InvalidChecksumException $e) {
    http_response_code(403); // not authentic
    exit;
} catch (\Setono\Quickpay\Exception\InvalidCallbackException $e) {
    http_response_code(400); // unknown resource type (or a malformed body)
    exit;
}

if ($callback->isPayment()) {
    $payment = $callback->payment();        // typed Payment
    // ... handle $payment->state(), $payment->accepted, $payment->operations, $payment->raw ...
} elseif (ResourceType::Subscription === $callback->type) {
    // a subscription — inspect $callback->toArray()
}

// Respond 2xx so Quickpay marks the callback as delivered.
http_response_code(200);
```

#### Without a PSR-7 request: Symfony, Laravel, plain PHP

Symfony and Laravel requests aren't PSR-7 (without a bridge), so in a controller use `handleRaw()`
with the raw body and header values you already have — it returns the same verified `Callback`:

```php
// Symfony controller (Symfony\Component\HttpFoundation\Request $request)
$callback = $handler->handleRaw(
    $request->getContent(),
    (string) $request->headers->get('QuickPay-Checksum-Sha256'),
    (string) $request->headers->get('QuickPay-Resource-Type'),
    accountId: $request->headers->get('QuickPay-Account-ID'),
    apiVersion: $request->headers->get('QuickPay-API-Version'),
);

// Laravel controller (Illuminate\Http\Request $request)
$callback = $handler->handleRaw(
    $request->getContent(),
    (string) $request->header('QuickPay-Checksum-Sha256'),
    (string) $request->header('QuickPay-Resource-Type'),
    accountId: $request->header('QuickPay-Account-ID'),
    apiVersion: $request->header('QuickPay-API-Version'),
);
```

Plain PHP (no framework)? `handleGlobals()` reads `php://input` and the `QuickPay-*` headers from
`$_SERVER` for you:

```php
$callback = $handler->handleGlobals();
```

Whichever entry point you use, keep the body **raw** — `$request->getContent()` is the raw body in
both frameworks; never re-encode a decoded JSON payload. To only verify (without wrapping), use
`CallbackValidator`.

#### Testing your callback endpoint

Don't mock `CallbackHandler` — it's `final` on purpose. Verification is deterministic and needs no
I/O, so use a **real handler with a made-up test key** and forge authentic signatures with
`$handler->validator()->sign()`. A mocked handler would let your controller test pass while the real
integration breaks: the checksum is computed over the raw, byte-for-byte body, and the most common
callback bug is a framework decoding/re-encoding or consuming that body before verification — which
only a real round-trip catches.

```php
use Setono\Quickpay\Callback\CallbackHandler;
use Setono\Quickpay\Enum\ResourceType;

$handler = new CallbackHandler('test-private-key'); // any string works as the key in tests

$body = '{"id":1234,"order_id":"order-0001","accepted":true}'; // or a captured callback fixture
$checksum = $handler->validator()->sign($body);                // forge an authentic signature

// Drive your endpoint: POST $body with the QuickPay-Checksum-Sha256 and QuickPay-Resource-Type
// headers set (your app wired with the same test key), or call the handler directly:
$callback = $handler->handleRaw($body, $checksum, ResourceType::Payment->value);

// And pin the rejection path — a tampered body must NOT be accepted:
$handler->handleRaw($body . 'tampered', $checksum, ResourceType::Payment->value); // throws InvalidChecksumException
```

#### Handling callbacks robustly

A few facts about Quickpay's callback service shape how your endpoint should behave (all from
[their callback docs](https://learn.quickpay.net/tech-talk/api/callback/), verified in the e2e harness):

- **Every operation triggers a callback, and the body is the whole payment as it exists after the
  change** (equivalent to `GET /payments/{id}`) — not a "capture succeeded" event. Callbacks for
  operations *you* issue via the API go to the account-wide callback URL unless you pass
  `callbackUrl:` on the operation (see [Capturing, refunding, cancelling](#capturing-refunding-cancelling)). Work out what
  happened from the operations: `$payment->latestOperation()` is usually the one that fired it, but
  compare against what you've already recorded rather than assuming.
- **Deliveries are retried up to 24 times** with growing delays until you answer `2xx` (or `302`/`303`).
  So make the endpoint **idempotent**: keep the operation ids you have processed per payment
  (`Operation::$id` is a per-payment sequence number) and skip ones you've seen. Answer `200` even
  when the payment is already in the state the callback describes.
- **Answer fast, then work.** Verify, persist, respond — and do the slow parts (emails, ERP sync)
  asynchronously. A slow endpoint looks like a failure and gets retried.
- **Order is only guaranteed per payment.** Callbacks for the same payment arrive in operation order;
  callbacks for different payments arrive in any order.
- **Respond `403` on a bad checksum and `400` on an unknown resource type**, as in the example above.
  A `2xx` on a bad checksum tells an attacker their forgery was accepted; a `5xx` on a genuinely bad
  request just earns you 24 retries of the same bad request.
- The verified `Callback` carries `accountId` — useful if one endpoint serves several Quickpay
  accounts — and the raw `body` you can store for auditing.

### Accessing fields the SDK doesn't model

The SDK types the most commonly used fields; every response object also exposes the full decoded
payload (with the original snake_case keys from the Quickpay docs) via `$raw`:

```php
$payment = $client->payments()->getById(1234);
$payment->raw['text_on_statement'];
$payment->raw['acquirer'];
```

### Calling endpoints the SDK doesn't model

The SDK is deliberately narrow, but the `Client` is a complete, authenticated HTTP layer for the whole
Quickpay API: `get()`, `post()`, `put()`, `patch()` and `delete()` take a path relative to
`https://api.quickpay.net`, stamp the auth and `Accept-Version` headers, throw the same typed
exceptions on non-2xx responses, and return the decoded JSON body as an array (`[]` for `204 No
Content`). Bodies can be a typed `Payload` or a plain array — arrays are sent **as given**, so use the
snake_case keys from the Quickpay docs:

```php
// Renew an authorization (POST /payments/{id}/renew) — not modeled on PaymentsEndpoint
$raw = $client->post(sprintf('payments/%d/renew?synchronized', $payment->id));

// Subscriptions — a resource the SDK doesn't type at all
$subscription = $client->post('subscriptions', [
    'order_id' => 'sub-0001',
    'currency' => 'DKK',
    'description' => 'Monthly plan',
]);
$client->put(sprintf('subscriptions/%d/link', $subscription['id']), ['amount' => 9900, 'continue_url' => '...']);

// Anything else — a query string, a DELETE
$operations = $client->get(sprintf('payments/%d/operations/%d', $payment->id, 3));
$client->delete(sprintf('subscriptions/%d/link', $subscription['id']));
```

The host is pinned: an absolute URL to any other host throws `InvalidUrlException`, so your API key
can never leave `api.quickpay.net`. If you need the raw PSR-7 request/response, use
`$client->request($psr7Request)` (same guard, same headers) or `getLastRequest()` /
`getLastResponse()` after any call.

To turn a raw payment array (e.g. the `renew` response above) into a typed `Payment`, map it the way
the SDK does:

```php
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use Setono\Quickpay\Response\Payment\Payment;

$payment = Client::configureMapperBuilder(new MapperBuilder())->mapper()
    ->map(Payment::class, Source::array($raw)->camelCaseKeys());
$payment->raw = $raw;
```

### Error handling

Everything the SDK throws implements `Setono\Quickpay\Exception\QuickpayException`, so one catch
nets it all: every non-2xx response is a typed exception, a 2xx body that can't be decoded/mapped is
a `MalformedResponseException`, and a request that never got a response (DNS, connection refused,
TLS, timeout) is a `TransportException` wrapping the PSR-18 client's exception (it is still a PSR-18
`ClientExceptionInterface`; the original is `getPrevious()`):

```php
use Setono\Quickpay\Exception\QuickpayException;
use Setono\Quickpay\Exception\TransportException;
use Setono\Quickpay\Exception\ValidationException;

try {
    $client->payments()->create(new CreatePaymentRequest(orderId: 'dup-order-1', currency: 'DKK'));
} catch (ValidationException $e) {
    $e->getMessageText();       // Quickpay's "message"
    $e->getErrorCode();         // Quickpay's "error_code"
    $e->getValidationErrors();  // Quickpay's "errors" map (field => messages)
} catch (TransportException $e) {
    // nothing reached Quickpay (or nothing came back) — safe to retry an idempotent read;
    // for a create/capture, look the payment up first (see "Finding the payment for an order")
    $e->isNetworkError();  // vs. a request the HTTP client refused to send
    $e->getRequest();      // the PSR-7 request that failed
} catch (QuickpayException $e) {
    // any other SDK error (UnauthorizedException, NotFoundException, ConflictException,
    // TooManyRequestsException, InternalServerErrorException, MalformedResponseException, ...)
}
```

## Recipes

### Checkout, end to end

Putting the pieces together — create (idempotently), send the customer to pay, learn the outcome from
the callback, capture on shipment:

```php
use Setono\Quickpay\Request\Payment\CaptureRequest;
use Setono\Quickpay\Request\Payment\CreateLinkRequest;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;
use Setono\Quickpay\Request\Payment\Shopsystem;

// 1. Checkout: one Quickpay payment per order, safe to re-run
$payment = $client->payments()->findByOrderId($order->number)
    ?? $client->payments()->create(new CreatePaymentRequest(
        orderId: $order->number,          // 4–20 chars, unique per account
        currency: $order->currency,
        variables: ['order_uuid' => $order->uuid], // anything you want back on the callback
        shopsystem: new Shopsystem('acme/shop', '2.3.4'),
    ));

$link = $client->payments()->createLink($payment->id, new CreateLinkRequest(
    amount: $order->total,                // smallest unit
    continueUrl: $urls->thankYou($order),
    cancelUrl: $urls->checkout($order),
    callbackUrl: $urls->quickpayCallback(),
));
// → redirect the customer to $link->url

// 2. Callback endpoint: the source of truth (see "Callbacks")
$callback = $handler->handleRaw($request->getContent(), $checksum, $resourceType);
if ($callback->isPayment()) {
    $payment = $callback->payment();
    $order = $orders->findByQuickpayVariables($payment->variables()); // or by $payment->orderId
    foreach ($payment->operations as $operation) {
        if ($order->hasProcessedOperation($operation->id)) {
            continue; // retried delivery — idempotent
        }
        if ($operation->isOfType(OperationType::Authorize) && $operation->isApproved()) {
            $order->markAuthorized($payment->id, $operation->amount);
        }
        // ... capture / refund / cancel likewise, or simply store $payment->capturedAmount() etc.
        $order->recordOperation($operation->id);
    }
}
// respond 200

// 3. Shipping: capture (synchronously here, so a failure surfaces right away)
$payment = $client->payments()->capture($order->quickpayId, new CaptureRequest($order->total), synchronized: true);
if (!$payment->latestOperation()?->isApproved()) {
    // rejected — see qpStatusMsg / aqStatusMsg
}
```

### Testing your integration

`Client` and the endpoints are `final` on purpose: the seam for tests is the **HTTP client**. Inject
whatever PSR-18 fake your stack already has — `php-http/mock-client`, Symfony's `MockHttpClient`
behind `Psr18Client`, Guzzle's `MockHandler` — via `new Client('test-key', httpClient: $fake)` and
feed it captured JSON bodies (`$client->getLastResponse()` gives you real ones). Your tests then
exercise the SDK's real request building, mapping and error handling. Response DTOs have public
constructors, so code that merely *consumes* a `Payment` can be tested with hand-built objects; for
callbacks see [Testing your callback endpoint](#testing-your-callback-endpoint).

### Wiring in Symfony

The client and handler are plain, immutable services; give them their keys from the environment and
a Valinor cache from the app's cache directory:

```yaml
# config/services.yaml
services:
    CuyZ\Valinor\Cache\FileSystemCache:
        arguments: ['%kernel.cache_dir%/valinor']

    Setono\Quickpay\Client\ClientInterface:
        class: Setono\Quickpay\Client\Client
        arguments:
            $apiKey: '%env(QUICKPAY_API_KEY)%'
            $cache: '@CuyZ\Valinor\Cache\FileSystemCache'

    Setono\Quickpay\Callback\CallbackHandler:
        arguments:
            $privateKey: '%env(QUICKPAY_PRIVATE_KEY)%'
            $cache: '@CuyZ\Valinor\Cache\FileSystemCache'
```

The PSR-18 client and PSR-17 factories are discovered automatically; to use the app's own (e.g.
Symfony's `Psr18Client`), pass them explicitly via `$httpClient`, `$requestFactory`, `$streamFactory`.

## Production usage

Valinor's mapping/normalization is fast but benefits from a cache in production. Hand the client
(and the callback handler) a Valinor cache and the SDK wires it into its own, fully configured
builders:

```php
use CuyZ\Valinor\Cache\FileSystemCache;
use Setono\Quickpay\Callback\CallbackHandler;
use Setono\Quickpay\Client\Client;

$cache = new FileSystemCache(__DIR__ . '/var/cache/valinor'); // clear it on deploy, like any compiled cache

$client = new Client('YOUR_API_KEY', cache: $cache);
$handler = new CallbackHandler('YOUR_PRIVATE_KEY', cache: $cache);
```

If you already run Valinor elsewhere and want to share one builder, pass it explicitly — but wrap it
in the SDK's configuration first, or the response DTOs won't map (dates, extra keys). A builder you
pass in is used as given, and the `cache:` argument does not apply to it:

```php
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\NormalizerBuilder;

$client = new Client(
    'YOUR_API_KEY',
    mapperBuilder: Client::configureMapperBuilder((new MapperBuilder())->withCache($cache)),
    normalizerBuilder: Client::registerNormalizerTransformers((new NormalizerBuilder())->withCache($cache)),
);
```

## Contributing

```bash
composer install
composer phpunit              # tests
composer analyse             # PHPStan (level max)
composer check-style         # ECS
composer fix-style           # ECS, auto-fixing
composer rector -- --dry-run # Rector modernization (CI runs --dry-run)
vendor/bin/infection         # mutation testing (min covered MSI 70%)
composer e2e:smoke           # real-API smoke test (needs QUICKPAY_API_KEY — see examples/e2e/)
```

Live API tests are skipped unless `QUICKPAY_LIVE=1` and `QUICKPAY_API_KEY` are set. (Quickpay has no
separate test key — you use your real API key, and a payment is a *test* payment when paid with a
[test card](https://learn.quickpay.net/tech-talk/appendixes/test/).)

## End-to-end testing

Unit tests fake the HTTP layer; to verify the *whole* flow — create a payment, complete it in
Quickpay's hosted window with a test card, and receive and verify the signed asynchronous callback —
use the harness under [`examples/e2e/`](examples/e2e/README.md). It runs a local callback listener,
tunnels it to a public HTTPS URL with [Expose](https://expose.dev) (a small client Dockerfile is
included) so Quickpay can reach it, and gives you CLI scripts to create payments and drive
capture/refund/cancel:

```bash
composer e2e:listen     # terminal A: php -S 0.0.0.0:8000 listener that verifies callbacks
# terminal B: run the Expose tunnel (see examples/e2e/README.md)
QUICKPAY_CALLBACK_BASE=https://<your>.sharedwithexpose.com composer e2e:create -- 1000 DKK
composer e2e:operate -- capture <paymentId> 1000
```

See [`examples/e2e/README.md`](examples/e2e/README.md) for the full runbook, the test-card table, and
macOS/Docker networking notes.
