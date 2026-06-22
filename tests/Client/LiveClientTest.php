<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\Quickpay\Request\Payment\CreateLinkRequest;
use Setono\Quickpay\Request\Payment\CreatePaymentRequest;

/**
 * Hits the real Quickpay API. Skipped unless `QUICKPAY_LIVE=1` and `QUICKPAY_API_KEY` are set (use a
 * test API key). Not run in CI.
 *
 * @group live
 */
final class LiveClientTest extends TestCase
{
    private function liveClient(): Client
    {
        if ('1' !== getenv('QUICKPAY_LIVE')) {
            self::markTestSkipped('Set QUICKPAY_LIVE=1 to run the live API tests.');
        }

        $apiKey = getenv('QUICKPAY_API_KEY');
        if (!is_string($apiKey) || '' === $apiKey) {
            self::markTestSkipped('Set QUICKPAY_API_KEY (a test API key) to run the live API tests.');
        }

        return new Client($apiKey);
    }

    #[Test]
    public function it_pings_the_real_api(): void
    {
        self::assertTrue($this->liveClient()->ping());
    }

    #[Test]
    public function it_creates_a_payment_and_a_link(): void
    {
        $client = $this->liveClient();

        $payment = $client->payments()->create(new CreatePaymentRequest(
            orderId: 'sdk-probe-' . bin2hex(random_bytes(6)),
            currency: 'DKK',
        ));
        self::assertGreaterThan(0, $payment->id);

        $link = $client->payments()->createLink($payment->id, new CreateLinkRequest(
            amount: 1000,
            continueUrl: 'https://example.com/continue',
            cancelUrl: 'https://example.com/cancel',
        ));
        self::assertNotNull($link->url);
        self::assertStringStartsWith('https://', $link->url);
    }
}
