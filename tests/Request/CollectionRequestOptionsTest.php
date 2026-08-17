<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionRequestOptionsTest extends TestCase
{
    #[Test]
    public function it_rejects_a_page_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CollectionRequestOptions(0);
    }

    #[Test]
    public function it_rejects_a_page_size_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CollectionRequestOptions(1, 0);
    }

    #[Test]
    public function it_accepts_the_lower_bound(): void
    {
        $opts = new CollectionRequestOptions(1, 1);

        self::assertSame(['page' => 1, 'page_size' => 1], $opts->toArray());
    }

    #[Test]
    public function it_maps_to_the_page_and_page_size_query_params(): void
    {
        self::assertSame(['page' => 2, 'page_size' => 50], (new CollectionRequestOptions(2, 50))->toArray());
    }

    #[Test]
    public function it_builds_modified_copies_immutably(): void
    {
        $opts = new CollectionRequestOptions();

        self::assertSame(1, $opts->page);
        self::assertSame(20, $opts->pageSize);
        self::assertSame(3, $opts->withPage(3)->page);
        self::assertSame(99, $opts->withPageSize(99)->pageSize);
        // The original is untouched.
        self::assertSame(1, $opts->page);
        self::assertSame(20, $opts->pageSize);
    }

    #[Test]
    public function the_withers_validate_too(): void
    {
        $opts = new CollectionRequestOptions();

        try {
            $opts->withPage(0);
            self::fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Expected $page to be at least 1, got 0.', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected $pageSize to be at least 1, got -5.');
        $opts->withPageSize(-5);
    }
}
