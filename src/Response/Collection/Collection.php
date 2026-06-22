<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response\Collection;

/**
 * Passive data carrier for a single page of a paginated Quickpay resource.
 *
 * Quickpay list endpoints return a bare JSON array of items and do NOT send any total-count
 * headers, so `$totalCount` / `$totalPages` are best-effort (set to the items seen on this page and
 * `0` respectively) and should not be relied upon. Pagination is driven entirely by `$page` /
 * `$pageSize`; the walking logic lives in
 * {@see \Setono\Quickpay\Client\Endpoint\CollectionEndpoint::paginate()}, not here.
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
final class Collection implements \IteratorAggregate, \Countable
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page = 1,
        public readonly int $pageSize = 0,
        public readonly int $totalCount = 0,
        public readonly int $totalPages = 0,
    ) {
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * @param \Closure(T):bool $predicate
     *
     * @return self<T>
     */
    public function filter(\Closure $predicate): self
    {
        return new self(
            array_values(array_filter($this->items, $predicate)),
            $this->page,
            $this->pageSize,
            $this->totalCount,
            $this->totalPages,
        );
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}
