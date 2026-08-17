# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package follows
[Semantic Versioning](https://semver.org/) (see [UPGRADE.md](UPGRADE.md) for breaking changes between
major versions). Every entry links the pull request; the
[GitHub releases](https://github.com/Setono/quickpay-php-sdk/releases) carry the same notes with more
narrative.

## [Unreleased]

## [1.2.0] — 2026-08-17

Two follow-ups from reviewing `setono/payum-quickpay` against 1.1 — Quickpay knowledge every
integration re-derives ([#22](https://github.com/Setono/quickpay-php-sdk/issues/22),
[#25](https://github.com/Setono/quickpay-php-sdk/issues/25)). Additive; see "BC notes".

### Added

- **Per-operation callback URL.** `authorize()` / `capture()` / `refund()` / `cancel()` take a
  `callbackUrl` argument, sent as the `QuickPay-Callback-Url` header (`Client::CALLBACK_URL_HEADER`)
  so Quickpay POSTs *that operation's* callback there — by default API-issued operations notify only
  the account-wide callback URL (empty by default), not the payment link's `callback_url`. The
  low-level `get()` / `post()` / `put()` / `patch()` / `delete()` accept extra request headers, and
  `ResourceEndpoint::postOperation()` takes `$headers`
  ([#26](https://github.com/Setono/quickpay-php-sdk/pull/26)).
- `Link::$autoCapture` (`?bool`) and `Link::$autoCaptureAt` (`?string`)
  ([#26](https://github.com/Setono/quickpay-php-sdk/pull/26)).
- Outcome predicates on `Operation`: `hasOutcome()` (no longer pending) and `isDeclined()`
  (completed without being approved — a *synchronized* decline is a `2xx` with the decline on the
  operation) ([#27](https://github.com/Setono/quickpay-php-sdk/pull/27)).
- Status views on `Payment`: `latestOperationOfType()`, `latestApprovedOperation()`,
  `hasApprovedOperation(?type)`; `hasPendingOperation()` takes an optional type. "Latest" is always
  the highest operation id ([#27](https://github.com/Setono/quickpay-php-sdk/pull/27)).
- `CreatePaymentRequest::ORDER_ID_PATTERN` / `assertValidOrderId()`, and
  `CreateLinkRequest::$paymentMethods` accepts a list (`['creditcard', '!amex']`) and joins it the
  way Quickpay expects ([#27](https://github.com/Setono/quickpay-php-sdk/pull/27)).
- `Setono\Quickpay\Exception\InvalidArgumentException` — an SPL `\InvalidArgumentException` that
  is also a `QuickpayException`, thrown for request values the API is known to reject
  ([#27](https://github.com/Setono/quickpay-php-sdk/pull/27)).
- README: where operation callbacks go; `state` reads `pending` during *any* asynchronous operation
  (with the pre-operation `balance`); Quickpay does not retry a declined operation; synchronized
  declines are `2xx`; the `orderId` rule; the new helpers with a decline-check snippet
  ([#26](https://github.com/Setono/quickpay-php-sdk/pull/26), [#27](https://github.com/Setono/quickpay-php-sdk/pull/27)).

### Changed

- `CreatePaymentRequest` now validates `orderId` **at construction** — 4–20 characters of letters,
  digits, space, `.`, `_`, `-`, verified against the live API, which rejects everything else under a
  misleading "must have length between 4 and 20" message — and throws
  `Setono\Quickpay\Exception\InvalidArgumentException` before any request is made. Code that relied
  on the API's `ValidationException` for a bad `order_id` now fails earlier, with a message naming the
  actual rule ([#27](https://github.com/Setono/quickpay-php-sdk/pull/27)).
- `CollectionRequestOptions` throws `Setono\Quickpay\Exception\InvalidArgumentException` (a subclass
  of the SPL exception it threw before) ([#27](https://github.com/Setono/quickpay-php-sdk/pull/27)).
- `examples/e2e/operate.php` passes the listener's `/callback` as the per-operation callback URL
  when `QUICKPAY_CALLBACK_BASE` is set ([#26](https://github.com/Setono/quickpay-php-sdk/pull/26)).

### BC notes

- `ClientInterface::get()/post()/put()/patch()/delete()` and the protected
  `ResourceEndpoint::postOperation()` gained an optional `$headers` parameter — a change only for
  code that *implements* the interface (a mock-only interface; `Client` is its sole implementation)
  or *subclasses* the SDK's endpoint base (unsupported). Callers are unaffected. The Roave BC check
  reported these and was knowingly accepted red for #26
  ([#26](https://github.com/Setono/quickpay-php-sdk/pull/26)).

## [1.1.0] — 2026-08-17

Developer-experience follow-ups from the v1.0.0 review ([#10](https://github.com/Setono/quickpay-php-sdk/issues/10)).
All additive; see "BC notes" for the two things that could touch unusual code.

### Added

- `PaymentsEndpoint::findByOrderId(string): ?Payment` and a typed `PaymentsQuery` (filters for
  `GET /payments`: `orderId`, `state`, `accepted`, `minTime`/`maxTime`, `acquirer`, `fraudSuspected`,
  `id`, `sortBy`/`sortDir`, `operationsSize`, `extra`) usable with `getPage()`/`paginate()`
  ([#12](https://github.com/Setono/quickpay-php-sdk/pull/12)).
- Result helpers: `Operation::isApproved()`, `Operation::isOfType()`, `Operation::QP_STATUS_APPROVED`;
  `Payment::operation()`, `latestOperation()`, `operationsOfType()`, `hasPendingOperation()`,
  `authorizedAmount()`, `capturedAmount()`, `refundedAmount()`, `isCancelled()`
  ([#13](https://github.com/Setono/quickpay-php-sdk/pull/13)).
- `Client::delete()`, plain-array bodies on `post()`/`put()`/`patch()` (`Payload|array $body = []`
  — never null; an empty body is sent as `{}`), and `PaymentsEndpoint::deleteLink()`; a
  `204 No Content` response decodes to `[]` ([#14](https://github.com/Setono/quickpay-php-sdk/pull/14)).
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
- README: table of contents, "Concepts", callback best practices, framework snippets, recipes, and
  sections on the escape hatch and error handling
  ([#12](https://github.com/Setono/quickpay-php-sdk/pull/12)–[#20](https://github.com/Setono/quickpay-php-sdk/pull/20)).
- `composer.json` keywords / homepage / support; this changelog
  ([#21](https://github.com/Setono/quickpay-php-sdk/pull/21)).

### Changed

- `CollectionRequestOptions` is no longer `final` (so `PaymentsQuery` can extend it); its withers
  clone, and `page`/`pageSize` are plain public properties (a readonly property cannot be
  reinitialized during clone before PHP 8.3) ([#12](https://github.com/Setono/quickpay-php-sdk/pull/12)).
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

- `ClientInterface` gains `delete()`, and `post()`/`put()`/`patch()` take `Payload|array $body = []`
  instead of `?Payload $body = null` — a change only for code that *implements* the interface, or
  that passed an explicit `null` body (pass nothing, or `[]`) ([#14](https://github.com/Setono/quickpay-php-sdk/pull/14)).
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

[Unreleased]: https://github.com/Setono/quickpay-php-sdk/compare/v1.2.0...1.x
[1.2.0]: https://github.com/Setono/quickpay-php-sdk/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-beta.2...v1.0.0
[1.0.0-beta.2]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-beta.1...v1.0.0-beta.2
[1.0.0-beta.1]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-alpha.4...v1.0.0-beta.1
[1.0.0-alpha.4]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-alpha.3...v1.0.0-alpha.4
[1.0.0-alpha.3]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-alpha.2...v1.0.0-alpha.3
[1.0.0-alpha.2]: https://github.com/Setono/quickpay-php-sdk/compare/v1.0.0-alpha.1...v1.0.0-alpha.2
[1.0.0-alpha.1]: https://github.com/Setono/quickpay-php-sdk/releases/tag/v1.0.0-alpha.1
