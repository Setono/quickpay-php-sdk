# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package follows
[Semantic Versioning](https://semver.org/) (see [UPGRADE.md](UPGRADE.md) for breaking changes between
major versions). Every entry links the pull request; the
[GitHub releases](https://github.com/Setono/quickpay-php-sdk/releases) carry the same notes with more
narrative.

## [Unreleased]

Developer-experience follow-ups from the v1.0.0 review ([#10](https://github.com/Setono/quickpay-php-sdk/issues/10)).

### Added

- `PaymentsEndpoint::findByOrderId(string): ?Payment` and a typed `PaymentsQuery` (filters for
  `GET /payments`: `orderId`, `state`, `accepted`, `minTime`/`maxTime`, `acquirer`, `fraudSuspected`,
  `id`, `sortBy`/`sortDir`, `operationsSize`, `extra`) usable with `getPage()`/`paginate()`
  ([#12](https://github.com/Setono/quickpay-php-sdk/pull/12)).
- Result helpers: `Operation::isApproved()`, `Operation::isOfType()`, `Operation::QP_STATUS_APPROVED`;
  `Payment::operation()`, `latestOperation()`, `operationsOfType()`, `hasPendingOperation()`,
  `authorizedAmount()`, `capturedAmount()`, `refundedAmount()`, `isCancelled()`
  ([#13](https://github.com/Setono/quickpay-php-sdk/pull/13)).
- `Client::delete()`, plain-array bodies on `post()`/`put()`/`patch()`, and
  `PaymentsEndpoint::deleteLink()`; a `204 No Content` response decodes to `[]`
  ([#14](https://github.com/Setono/quickpay-php-sdk/pull/14)).
- `cache:` constructor argument on `Client` and `CallbackHandler`; `Client::defaultMapperBuilder()` /
  `defaultNormalizerBuilder()` are public ([#16](https://github.com/Setono/quickpay-php-sdk/pull/16)).
- `CallbackHandler::handleGlobals()`; `handleRaw()` accepts the optional `accountId` / `apiVersion`
  headers ([#17](https://github.com/Setono/quickpay-php-sdk/pull/17)).
- `TransportException` (a `QuickpayException` **and** a PSR-18 `ClientExceptionInterface`) wrapping
  transport failures, so `catch (QuickpayException $e)` nets everything the SDK throws
  ([#18](https://github.com/Setono/quickpay-php-sdk/pull/18)).
- `Payment::variables()`, `Payment::$deadlineAt`, `Payment::$acquirer`; `CreatePaymentRequest::$shopsystem`
  (`Shopsystem` payload); the SDK version in the `User-Agent` (`Client::version()`)
  ([#19](https://github.com/Setono/quickpay-php-sdk/pull/19)).
- Per-operation callback URL: `authorize()`/`capture()`/`refund()`/`cancel()` take a `callbackUrl`
  argument, sent as the `QuickPay-Callback-Url` header (`Client::CALLBACK_URL_HEADER`) so Quickpay
  notifies that URL for the operation instead of the account-wide callback URL; the low-level
  `get()`/`post()`/`put()`/`patch()`/`delete()` accept extra request headers; `Link::$autoCapture` /
  `$autoCaptureAt` are typed ([#26](https://github.com/Setono/quickpay-php-sdk/pull/26), closes #22).
- Outcome predicates and status views: `Operation::hasOutcome()` / `isDeclined()`;
  `Payment::latestOperationOfType()`, `latestApprovedOperation()`, `hasApprovedOperation(?type)`,
  and `hasPendingOperation()` now takes an optional type ([#27](https://github.com/Setono/quickpay-php-sdk/pull/27), closes #25).
- `CreatePaymentRequest` validates `orderId` at construction (`ORDER_ID_PATTERN`: 4–20 characters
  of letters, digits, space, `.`, `_`, `-` — verified live) and throws the new
  `Setono\Quickpay\Exception\InvalidArgumentException` (an SPL `InvalidArgumentException` that is
  also a `QuickpayException`; `CollectionRequestOptions` now throws it too);
  `CreateLinkRequest::$paymentMethods` accepts a list and joins it ([#27](https://github.com/Setono/quickpay-php-sdk/pull/27)).
- README: table of contents, "Concepts", callback best practices, framework snippets, recipes, and
  sections on the escape hatch and error handling
  ([#12](https://github.com/Setono/quickpay-php-sdk/pull/12)–[#20](https://github.com/Setono/quickpay-php-sdk/pull/20)).
- `composer.json` keywords / homepage / support; this changelog
  ([#21](https://github.com/Setono/quickpay-php-sdk/pull/21)).

### Changed

- `CollectionRequestOptions` is no longer `final` (so `PaymentsQuery` can extend it)
  ([#12](https://github.com/Setono/quickpay-php-sdk/pull/12)).
- `Callback::payment()` is memoized ([#19](https://github.com/Setono/quickpay-php-sdk/pull/19)).
- `README.md` and `UPGRADE.md` are shipped in the dist again
  ([#21](https://github.com/Setono/quickpay-php-sdk/pull/21)).

### Fixed

- `Client::request()` now runs the same host-pinning guard as `get()`/`post()`/…, so a
  consumer-built request can never carry the API key to another host
  ([#11](https://github.com/Setono/quickpay-php-sdk/pull/11)).
- `ClientInterface::put()` docblock said it updates payments (that is `patch()`)
  ([#11](https://github.com/Setono/quickpay-php-sdk/pull/11)).

### BC notes

- `ClientInterface` gains `delete()` and wider `$body` types on `post()`/`put()`/`patch()` — a
  change only for code that *implements* the interface ([#14](https://github.com/Setono/quickpay-php-sdk/pull/14)).
- Code that caught `Psr\Http\Client\NetworkExceptionInterface` / `RequestExceptionInterface`
  *specifically* should catch `TransportException` (or `ClientExceptionInterface`) and inspect
  `getPrevious()` / `isNetworkError()` ([#18](https://github.com/Setono/quickpay-php-sdk/pull/18)).

## [1.0.0] — 2026-08-10

First stable release. A small, strongly-typed SDK for the Quickpay API (v10), deliberately focused on
the `payments` resource, the `/ping` health check, the payment-window **link** flow, and **callback**
(webhook) verification. Requires PHP >= 8.1 and any PSR-18 client / PSR-17 factories.

### Changed since 1.0.0-beta.2

- Dist installs are lean: `CLAUDE.md`, `examples/` and `.env.local.example` are `export-ignore`d
  ([#9](https://github.com/Setono/quickpay-php-sdk/pull/9)).

### Fixed

- The live test's generated `order_id` exceeded Quickpay's 20-character limit ([#9](https://github.com/Setono/quickpay-php-sdk/pull/9)).
- A docblock referenced the nonexistent `CallbackHandler::handleRequest()` (now `handleRaw()`) ([#9](https://github.com/Setono/quickpay-php-sdk/pull/9)).
- An undefined `env.PHP_EXTENSIONS` reference in the CI workflow ([#9](https://github.com/Setono/quickpay-php-sdk/pull/9)).

## [1.0.0-beta.2] — 2026-08-10

### Changed

- `webmozart/assert` is no longer a dependency; `CollectionRequestOptions` uses inline guards throwing
  `\InvalidArgumentException` ([#8](https://github.com/Setono/quickpay-php-sdk/pull/8)).

### Removed (BC break)

- `CollectionRequestOptions::new()` — use `new CollectionRequestOptions(pageSize: 50)` ([#8](https://github.com/Setono/quickpay-php-sdk/pull/8)).

## [1.0.0-beta.1] — 2026-08-06

First beta — the API surface considered stable for 1.0.

### Added

- README: "Testing your callback endpoint" ([#7](https://github.com/Setono/quickpay-php-sdk/pull/7)).

## [1.0.0-alpha.4] — 2026-08-06

### Changed

- `ResourceEndpoint` helpers renamed for consistency: `update()` → `updateOne()`, `operation()` →
  `postOperation()`, `putSub()` → `putSubResource()` (protected, outside the BC promise)
  ([#6](https://github.com/Setono/quickpay-php-sdk/pull/6)).
- `ResponseAwareException` fully covered by tests; MSI 80% → 85% ([#5](https://github.com/Setono/quickpay-php-sdk/pull/5)).

## [1.0.0-alpha.3] — 2026-08-06

### Changed (BC break)

- Unconditionally-required request fields (verified against the live API) are required constructor
  parameters: `CreatePaymentRequest::$orderId`/`$currency`; `$amount` on `CreateLinkRequest`,
  `CaptureRequest`, `RefundRequest`, `AuthorizePaymentRequest`; all five `BasketItem` fields;
  `PaymentsEndpoint::authorize()` requires its request ([#2](https://github.com/Setono/quickpay-php-sdk/pull/2), [#3](https://github.com/Setono/quickpay-php-sdk/pull/3)).

### Fixed

- A `Payload` with no set fields was sent as `[]` (rejected by the API); it is now `{}` ([#2](https://github.com/Setono/quickpay-php-sdk/pull/2)).
- `updatePayment()`'s docblock said PUT; the SDK sends PATCH ([#4](https://github.com/Setono/quickpay-php-sdk/pull/4)).

### Added

- Docblocks explaining what the async (202) operation response contains; test-suite audit
  (92 → 103 tests, MSI 72% → 80%) ([#4](https://github.com/Setono/quickpay-php-sdk/pull/4)).

## [1.0.0-alpha.2] — 2026-08-06

### Added

- Client-wide default for the `synchronized` operation flag (`Client::__construct(..., synchronized:)`,
  `ClientInterface::isSynchronized()`); operation methods take `?bool $synchronized = null`
  ([#1](https://github.com/Setono/quickpay-php-sdk/pull/1)).

## [1.0.0-alpha.1] — 2026-06-30

First alpha: payments (create/get/list/update/authorize/capture/refund/cancel), the payment-window
link flow, signed callback verification, `/ping`, typed DTOs with a `$raw` fallback, a typed
exception hierarchy under `QuickpayException`, and host pinning.

[Unreleased]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0...1.x
[1.0.0]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-beta.2...v1.0.0
[1.0.0-beta.2]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-beta.1...v1.0.0-beta.2
[1.0.0-beta.1]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-alpha.4...v1.0.0-beta.1
[1.0.0-alpha.4]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-alpha.3...v1.0.0-alpha.4
[1.0.0-alpha.3]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-alpha.2...v1.0.0-alpha.3
[1.0.0-alpha.2]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-alpha.1...v1.0.0-alpha.2
[1.0.0-alpha.1]: https://github.com/Setono/quickpay-php-sdk/releases/tag/v1.0.0-alpha.1
