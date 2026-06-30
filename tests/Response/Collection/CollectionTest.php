<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Collection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    #[Test]
    public function an_empty_collection_has_the_expected_defaults(): void
    {
        $collection = new Collection([]);

        self::assertCount(0, $collection);
        self::assertTrue($collection->isEmpty());
        self::assertSame(1, $collection->page);
        self::assertSame(0, $collection->pageSize);
        self::assertSame(0, $collection->totalCount);
        self::assertSame(0, $collection->totalPages);
    }

    #[Test]
    public function it_exposes_items_pagination_and_supports_filtering(): void
    {
        $collection = new Collection([1, 2, 3], page: 2, pageSize: 3, totalCount: 9, totalPages: 3);

        self::assertCount(3, $collection);
        self::assertFalse($collection->isEmpty());
        self::assertSame(1, $collection->first());
        self::assertSame([1, 2, 3], iterator_to_array($collection));
        self::assertSame(2, $collection->page);
        self::assertSame(3, $collection->pageSize);
        self::assertSame(9, $collection->totalCount);
        self::assertSame(3, $collection->totalPages);

        $even = $collection->filter(static fn (int $n): bool => 0 === $n % 2);
        self::assertSame([2], $even->items);
        // filter() preserves the pagination metadata.
        self::assertSame(2, $even->page);
        self::assertSame(9, $even->totalCount);
    }
}
