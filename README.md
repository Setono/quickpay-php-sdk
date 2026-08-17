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

## Installation

```bash
composer require setono/quickpay-php-sdk
```

You also need a PSR-18 client and a PSR-17 factory if your project doesn't already provide them, e.g.:

```bash
composer require kriswallsmith/buzz nyholm/psr7
```

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
field is optional and simply omitted from the request JSON when unset.

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

### Updating a payment

Before a payment is authorized you can update some of its fields (`PATCH /payments/{id}`). Note the
API does not allow changing `order_id` or `basket` after creation:

```php
use Setono\Quickpay\Request\Payment\UpdatePaymentRequest;

$client->payments()->updatePayment($payment->id, new UpdatePaymentRequest(
    variables: ['internal_ref' => 'abc-123'],
));
```

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

No PSR-7 request handy? Use `handleRaw($rawBody, $checksum, $resourceType)` with the raw body and
header values — e.g. `file_get_contents('php://input')`, `$_SERVER['HTTP_QUICKPAY_CHECKSUM_SHA256']`,
`$_SERVER['HTTP_QUICKPAY_RESOURCE_TYPE']`. This is also the one to use if your framework already
consumed the request body. To only verify (without wrapping), use `CallbackValidator`.

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

### Testing code that uses the SDK

`Client` and the endpoints are `final` on purpose: the seam for tests is the **HTTP layer**, so your
tests exercise the SDK's real request building, (de)serialization and error mapping instead of a
mock that agrees with your assumptions. The SDK ships an in-process PSR-18 fake for exactly this —
`Setono\Quickpay\Testing\ScriptedHttpClient` (it is what the SDK's own suite uses):

```php
use Setono\Quickpay\Client\Client;
use Setono\Quickpay\Testing\ScriptedHttpClient;

$http = (new ScriptedHttpClient())
    // key = URI (relative to https://api.quickpay.net, or absolute), value = JSON body
    ->on('payments/1234', '{"id":1234,"merchant_id":1,"order_id":"order-0001","accepted":true,"currency":"DKK","state":"new","operations":[]}')
    // optionally pin a method, status and headers; queries must match exactly as the SDK sends them
    ->on('POST payments', file_get_contents(__DIR__ . '/fixtures/payment.json'), 201)
    ->on('payments?order_id=order-0002&page=1&page_size=1', '[]')
    ->on('payments/999', '{"message":"Not found"}', 404);

$client = new Client('test-key', httpClient: $http); // any string works as the key in tests

// ... exercise your code, e.g. $checkout->start($order) ...

self::assertSame('POST', $http->sentRequests[0]->getMethod());
self::assertJsonStringEqualsJsonString('{"order_id":"order-0001","currency":"DKK"}', (string) $http->sentRequests[0]->getBody());
```

Capture the fixture bodies from real (test-mode) responses — `$client->getLastResponse()` gives you
the raw PSR-7 response after any call. A request with no script throws, listing what *is* scripted.

Response DTOs also have public constructors, so code that merely *consumes* a `Payment` (an event
listener, a state machine) can be tested with hand-built objects:

```php
use Setono\Quickpay\Response\Payment\Operation;
use Setono\Quickpay\Response\Payment\Payment;

$payment = new Payment(id: 1, orderId: 'order-0001', currency: 'DKK', state: 'new', merchantId: 1, accepted: true, operations: [
    new Operation(id: 1, type: 'authorize', amount: 1000, qpStatusCode: '20000'),
]);
```

For callbacks, see [Testing your callback endpoint](#testing-your-callback-endpoint) — same idea:
a real handler with a made-up key, and `sign()` to forge authentic signatures.

### Accessing fields the SDK doesn't model

The SDK types the most commonly used fields; every response object also exposes the full decoded
payload (with the original snake_case keys from the Quickpay docs) via `$raw`:

```php
$payment = $client->payments()->getById(1234);
$payment->raw['text_on_statement'];
$payment->raw['acquirer'];
```

### Error handling

Every non-2xx response throws a typed exception; all of them implement
`Setono\Quickpay\Exception\QuickpayException`:

```php
use Setono\Quickpay\Exception\QuickpayException;
use Setono\Quickpay\Exception\ValidationException;

try {
    $client->payments()->create(new CreatePaymentRequest(orderId: 'dup-order-1', currency: 'DKK'));
} catch (ValidationException $e) {
    $e->getMessageText();       // Quickpay's "message"
    $e->getErrorCode();         // Quickpay's "error_code"
    $e->getValidationErrors();  // Quickpay's "errors" map (field => messages)
} catch (QuickpayException $e) {
    // any other SDK error (UnauthorizedException, NotFoundException, ConflictException,
    // TooManyRequestsException, InternalServerErrorException, MalformedResponseException, ...)
}
```

## Production usage

Valinor's mapping/normalization is fast but benefits from a cache in production. Wrap your own
builders with the SDK's configuration and pass them to the client:

```php
use CuyZ\Valinor\Cache\FileSystemCache;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\NormalizerBuilder;
use Setono\Quickpay\Client\Client;

$cache = new FileSystemCache(__DIR__ . '/var/cache/valinor');

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
