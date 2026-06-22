<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client\Endpoint;

use PHPUnit\Framework\Attributes\Test;
use Setono\Quickpay\QuickpayTestCase;
use Setono\Quickpay\Request\CollectionRequestOptions;
use Setono\Quickpay\Response\Payment\Payment;
use Setono\Quickpay\TestDouble\ScriptedHttpClient;

final class PaginationTest extends QuickpayTestCase
{
    #[Test]
    public function it_walks_pages_until_a_short_page_is_returned(): void
    {
        $http = (new ScriptedHttpClient())
            ->on(self::BASE . '/payments?page=1&page_size=2', self::payments(1, 2))
            ->on(self::BASE . '/payments?page=2&page_size=2', self::payments(3))
        ;

        /** @var list<Payment> $items */
        $items = iterator_to_array($this->client($http)->payments()->paginate(new CollectionRequestOptions(1, 2)), false);

        self::assertCount(3, $items);
        self::assertSame([1, 2, 3], array_map(static fn (Payment $p): int => $p->id, $items));
        self::assertCount(2, $http->sentRequests, 'pagination should stop after the short second page');
    }

    #[Test]
    public function it_stops_on_an_empty_first_page(): void
    {
        $http = (new ScriptedHttpClient())->on(self::BASE . '/payments?page=1&page_size=2', '[]');

        $items = iterator_to_array($this->client($http)->payments()->paginate(new CollectionRequestOptions(1, 2)), false);

        self::assertCount(0, $items);
        self::assertCount(1, $http->sentRequests);
    }

    private static function payments(int ...$ids): string
    {
        $payments = array_map(static fn (int $id): array => [
            'id' => $id,
            'merchant_id' => 1,
            'order_id' => 'o-' . $id,
            'accepted' => true,
            'currency' => 'DKK',
            'state' => 'new',
            'test_mode' => true,
            'operations' => [],
        ], $ids);

        return json_encode($payments, \JSON_THROW_ON_ERROR);
    }
}
