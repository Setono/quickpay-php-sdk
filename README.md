# Quickpay PHP SDK

[![Latest Stable Version](https://poser.pugx.org/setono/quickpay-php-sdk/v/stable)](https://packagist.org/packages/setono/quickpay-php-sdk)
[![License](https://poser.pugx.org/setono/quickpay-php-sdk/license)](https://packagist.org/packages/setono/quickpay-php-sdk)
[![Build Status](https://github.com/Setono/quickpay-php-sdk/actions/workflows/build.yaml/badge.svg)](https://github.com/Setono/quickpay-php-sdk/actions)

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
separate sandbox host — use a **test API key** to run in test mode.

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

```php
use Setono\Quickpay\Callback\CallbackHandler;

$handler = new CallbackHandler('YOUR_PRIVATE_KEY');

$rawBody  = file_get_contents('php://input');
$checksum = $_SERVER['HTTP_QUICKPAY_CHECKSUM_SHA256'] ?? '';

try {
    // Verifies the checksum AND deserializes the body into a Payment in one step.
    $payment = $handler->handle($rawBody, $checksum);
} catch (\Setono\Quickpay\Exception\InvalidChecksumException $e) {
    http_response_code(403);
    exit;
}

// Respond 2xx so Quickpay marks the callback as delivered.
http_response_code(200);
```

If you have a PSR-7 server request, `handleRequest($request)` reads the raw body and the checksum
header for you. To only verify (without deserializing), use `CallbackValidator`.

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
    $client->payments()->create(new CreatePaymentRequest(orderId: 'dup', currency: 'DKK'));
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
composer phpunit       # tests
composer analyse       # PHPStan (level max)
composer check-style   # ECS
composer fix-style     # ECS, auto-fixing
```

Live API tests are skipped unless `QUICKPAY_LIVE=1` and `QUICKPAY_API_KEY` (a test key) are set.
