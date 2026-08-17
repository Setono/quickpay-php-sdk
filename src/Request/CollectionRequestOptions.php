<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request;

use Setono\Quickpay\Exception\InvalidArgumentException;

/**
 * Immutable options for a paginated list request: which page and how many entries per page.
 *
 * Quickpay paginates list endpoints with the `page` and `page_size` query parameters and returns a
 * bare JSON array with no total-count headers, so {@see toArray()} maps directly to those two query
 * parameters.
 *
 * Resource-specific subclasses add typed filters on top — e.g.
 * {@see \Setono\Quickpay\Request\Payment\PaymentsQuery} for `GET /payments` — and merge them into
 * {@see toArray()}. The withers clone, so a subclass's filters are carried along automatically
 * while paginating. Subclassing is an SDK-internal extension point; consumers should use the
 * concrete query classes.
 *
 * `$page` / `$pageSize` are plain (non-readonly) public properties only so that the withers can
 * clone-and-set on PHP 8.1 (reinitializing a readonly property during clone needs PHP 8.3); go
 * through the constructor or the withers to keep the `>= 1` validation.
 */
class CollectionRequestOptions
{
    public function __construct(
        public int $page = 1,
        public int $pageSize = 20,
    ) {
        self::assertAtLeastOne('$page', $page);
        self::assertAtLeastOne('$pageSize', $pageSize);
    }

    /**
     * A copy for another page, keeping everything else (including a subclass's filters).
     */
    public function withPage(int $page): static
    {
        self::assertAtLeastOne('$page', $page);

        $copy = clone $this;
        $copy->page = $page;

        return $copy;
    }

    /**
     * A copy with another page size, keeping everything else (including a subclass's filters).
     */
    public function withPageSize(int $pageSize): static
    {
        self::assertAtLeastOne('$pageSize', $pageSize);

        $copy = clone $this;
        $copy->pageSize = $pageSize;

        return $copy;
    }

    /**
     * The query parameters for the list request. Subclasses merge their filters in and keep
     * `page` / `page_size` as the last two entries.
     *
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'page_size' => $this->pageSize,
        ];
    }

    private static function assertAtLeastOne(string $name, int $value): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException(sprintf('Expected %s to be at least 1, got %d.', $name, $value));
        }
    }
}
