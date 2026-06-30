# End-to-end test harness

This harness lets you verify the **whole** payment flow against the real Quickpay API — the part unit
tests can't cover: creating a payment, completing it in Quickpay's hosted payment window with a test
card, and receiving and **verifying the asynchronous callback** (webhook).

Because Quickpay must POST the callback to a public HTTPS URL, you tunnel a local listener to the
internet with [Expose](https://expose.dev).

```
                 ┌─────────────┐   create + link    ┌────────────────┐
 create-payment ─┤  Quickpay   │◄───────────────────┤  this machine  │
                 │     API     │   signed callback   │  (listener)    │
   browser ─────►│  payment    ├───────────────────►│  via Expose    │
   (test card)   │   window    │   POST /callback    │  tunnel        │
                 └─────────────┘                     └────────────────┘
```

## How Quickpay test mode works (important)

- There is **no sandbox and no separate test API key.** You use your **real production API key**.
- A payment becomes a *test* payment purely by paying with a **special test card number** (below).
  It's marked with `test_mode: true` and never touches real money.
- Test payments fire **real, signed callbacks** exactly like production — HMAC-SHA256 over the raw body
  using your **account private key** (Quickpay manager → Settings → Integration), which is **different**
  from the API key.
- The browser redirect to `continueUrl` carries **no data** and can arrive *before* the callback —
  trust only the verified callback (or `operate.php get`).

## Prerequisites

- A clone of this repo with dev dependencies installed: `composer install` (the harness uses the
  buzz + nyholm dev deps via PSR auto-discovery — there's nothing to wire manually).
- Two Quickpay credentials. Put them in a gitignored `.env.local` at the repo root (the harness loads
  it automatically), or export them as env vars (real env vars win over the file):
  ```bash
  cp .env.local.example .env.local   # then fill in QUICKPAY_API_KEY + QUICKPAY_PRIVATE_KEY
  # ...or:
  export QUICKPAY_API_KEY=...        # Settings > API user
  export QUICKPAY_PRIVATE_KEY=...    # Settings > Integration
  ```
- A free [expose.dev](https://expose.dev) account and its auth token (for the callback half).

## Quick real-API check (no browser/tunnel)

Verify auth + create-payment + create-link against the live API. Charges nothing (no card is entered):

```bash
composer e2e:smoke
```

## Run it (three terminals)

### Terminal A — the callback listener

Bind to `0.0.0.0` so the Expose container can reach it:

```bash
composer e2e:listen
# = php -S 0.0.0.0:8000 examples/e2e/listen.php
```

It logs every verified callback to the terminal and to `examples/e2e/var/callbacks.log` (also viewable
at `http://localhost:8000/`).

### Terminal B — the Expose tunnel

Build the client image once, then share the listener. On **macOS / Docker Desktop**, reach the host via
`host.docker.internal` (`--network host` does not work there):

```bash
docker build -t expose-client examples/e2e/expose
docker run --rm -e EXPOSE_TOKEN=<your-token> expose-client \
  share http://host.docker.internal:8000 --server=eu-1
```

`http://192.168.2.100:8000` (your LAN IP) also works if your firewall allows it. Copy the public
`https://<random>.<region>.sharedwithexpose.com` URL it prints — on the free tier it changes every
session.

> No Docker? Install the client on the host instead:
> ```bash
> composer global require exposedev/expose
> expose token <your-token>
> expose share http://localhost:8000 --server=eu-1
> ```

### Terminal C — create a payment

Pass the Expose URL as the callback base:

```bash
QUICKPAY_CALLBACK_BASE=https://<random>.<region>.sharedwithexpose.com \
  composer e2e:create -- 1000 DKK
```

It prints the payment id and a **payment-window URL** — open it in a browser and pay with a test card.

## Test cards

Any **valid-looking** expiry and CVD work (there are no fixed values). Tip: set the CVD to an ISO-3166
numeric country code to choose the card's issuing country. The card number's last digits select the
scenario. VISA shown in full; other brands follow the same last-digit pattern (full list in the
[test appendix](https://learn.quickpay.net/tech-talk/appendixes/test/)):

| VISA card | Scenario |
|---|---|
| `1000 0000 0000 0008` | Approved |
| `1000 0000 0000 0016` | Rejected |
| `1000 0000 0000 0024` | Card expired |
| `1000 0000 0000 0032` | Capture rejected |
| `1000 0000 0000 0040` | Refund rejected |
| `1000 0000 0000 0057` | Cancel rejected |
| `1000 0000 0000 0065` | Recurring rejected |
| `1000 0000 0000 0073` | 3-D Secure required (`qp_status_code 30100`) |
| `1000 0000 0000 0081` | Authorize once |
| `1000 0000 0000 0099` | Delayed in queue 60s (async / pending) |

Other brands' "Approved" card: Mastercard `1000 0100 0000 0007`, Dankort `1000 0200 0000 0006`, Amex
`1000 0300 0000 0005`, Maestro `1000 0400 0000 0004`, Visa Electron `1000 0500 0000 0003`. Force a 3-D
Secure challenge on *any* card by prefixing the payment method with `3d-` (e.g. `3d-visa`); the test
3-D Secure step auto-approves (no real OTP).

## Drive the rest of the lifecycle

After an approved authorization, capture / refund / cancel and watch each callback land in Terminal A:

```bash
composer e2e:operate -- get     <paymentId>
composer e2e:operate -- capture <paymentId> 1000
composer e2e:operate -- refund  <paymentId> 250
composer e2e:operate -- cancel  <paymentId>
```

These run with `?synchronized`, so the command prints the completed transaction immediately; the
asynchronous callback still arrives at the listener.

## What to verify

- Terminal A logs `CALLBACK OK` with `test_mode=true`, the expected `accepted` flag, the payment
  `state`, and the last operation's `qp_status_code` / `aq_status_code`.
- A tampered body or wrong key is rejected: the listener returns **403** and logs
  `CALLBACK REJECTED (bad checksum)` (this exercises `CallbackHandler`/`CallbackValidator`).
- Run different cards to see the branches: `…0008` approved → `…0032` capture-rejected →
  `…0073` 3-D Secure, etc. Only `30100` is documented verbatim by Quickpay — capture the actual
  `qp_status_code` for each card on the first run if you want to pin exact values.

## Security

Secrets come **only** from environment variables and are never written to disk by these scripts.
`examples/e2e/var/` (the callback log, which may contain payment data and masked card metadata) is
gitignored. If you enable test transactions on a production account, turn them back off under
Settings → Integration when you're done.
