<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\Quickpay\Enum\PaymentState;

final class PaymentsQueryTest extends TestCase
{
    #[Test]
    public function it_sends_only_pagination_when_no_filter_is_set(): void
    {
        self::assertSame(['page' => 1, 'page_size' => 20], (new PaymentsQuery())->toArray());
    }

    #[Test]
    public function it_maps_every_filter_to_its_query_parameter(): void
    {
        $query = new PaymentsQuery(
            orderId: 'order-0001',
            state: PaymentState::New,
            accepted: true,
            minTime: new \DateTimeImmutable('2026-08-01 00:00:00', new \DateTimeZone('UTC')),
            maxTime: new \DateTimeImmutable('2026-08-31 23:59:59', new \DateTimeZone('Europe/Copenhagen')),
            acquirer: 'clearhaus',
            fraudSuspected: false,
            id: 1234,
            sortBy: 'created_at',
            sortDir: 'asc',
            operationsSize: 5,
            extra: ['page_key' => 'abc'],
            page: 3,
            pageSize: 50,
        );

        self::assertSame([
            'page_key' => 'abc',
            'order_id' => 'order-0001',
            'state' => 'new',
            'accepted' => 'true',
            'min_time' => '2026-08-01 00:00:00 +0000',
            'max_time' => '2026-08-31 23:59:59 +0200',
            'acquirer' => 'clearhaus',
            'fraud_suspected' => 'false',
            'id' => 1234,
            'sort_by' => 'created_at',
            'sort_dir' => 'asc',
            'operations_size' => 5,
            'page' => 3,
            'page_size' => 50,
        ], $query->toArray());
    }

    #[Test]
    public function it_accepts_the_state_as_a_plain_string(): void
    {
        // Non-exhaustive enum: a state the SDK doesn't model yet must still be filterable.
        self::assertSame('brand_new_state', (new PaymentsQuery(state: 'brand_new_state'))->toArray()['state']);
    }

    #[Test]
    public function it_encodes_booleans_as_true_and_false(): void
    {
        // http_build_query would turn `false` into `0`; the API accepts both, but `true`/`false`
        // is what its docs show — and it's readable in logs.
        self::assertSame('false', (new PaymentsQuery(accepted: false))->toArray()['accepted']);
        self::assertSame('true', (new PaymentsQuery(fraudSuspected: true))->toArray()['fraud_suspected']);
    }

    #[Test]
    public function it_never_lets_extra_override_pagination(): void
    {
        $query = new PaymentsQuery(extra: ['page' => 99, 'page_size' => 99, 'foo' => 'bar'], page: 2, pageSize: 10);

        self::assertSame(['foo' => 'bar', 'page' => 2, 'page_size' => 10], $query->toArray());
    }

    #[Test]
    public function it_carries_the_filters_along_when_changing_page(): void
    {
        $query = new PaymentsQuery(orderId: 'order-0001', accepted: true, page: 1, pageSize: 10);

        $next = $query->withPage(2);
        $bigger = $query->withPageSize(100);

        self::assertSame(['order_id' => 'order-0001', 'accepted' => 'true', 'page' => 2, 'page_size' => 10], $next->toArray());
        self::assertSame(['order_id' => 'order-0001', 'accepted' => 'true', 'page' => 1, 'page_size' => 100], $bigger->toArray());
        // The original is untouched.
        self::assertSame(1, $query->page);
        self::assertSame(10, $query->pageSize);
    }

    #[Test]
    public function it_still_validates_pagination(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PaymentsQuery(orderId: 'x', page: 0);
    }
}
