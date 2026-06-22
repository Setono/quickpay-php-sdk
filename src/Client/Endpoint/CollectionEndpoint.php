<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client\Endpoint;

use Setono\Quickpay\Request\CollectionRequestOptions;
use Setono\Quickpay\Response\Collection\Collection;
use Setono\Quickpay\Response\Resource;

/**
 * Abstract base for leaf endpoints that return a paginated `Collection<T>`.
 *
 * Quickpay list endpoints return a bare JSON array of items and paginate via the `page` /
 * `page_size` query parameters with NO total-count headers, so {@see self::mapPage()} maps each item
 * and builds the `Collection` from the requested page/page-size only. {@see self::paginate()} walks
 * pages until a short or empty page is returned.
 *
 * @template T of Resource
 * @extends ResourceEndpoint<T>
 */
abstract class CollectionEndpoint extends ResourceEndpoint
{
    /**
     * Fetch a single page of the collection.
     *
     * @return Collection<T>
     */
    public function getPage(?CollectionRequestOptions $opts = null): Collection
    {
        $opts ??= new CollectionRequestOptions();

        return $this->mapPage($this->client->get(static::getPath(), $opts->toArray()), $opts);
    }

    /**
     * Walk all pages, yielding every item across all pages in server order.
     *
     * Because Quickpay sends no total-count metadata, pagination stops when a page returns fewer
     * items than the requested page size (the last page) or returns nothing at all.
     *
     * @return \Generator<int, T>
     */
    public function paginate(?CollectionRequestOptions $opts = null): \Generator
    {
        $opts ??= new CollectionRequestOptions();
        $page = $opts->page;

        while (true) {
            $current = $opts->withPage($page);
            $collection = $this->getPage($current);

            if ($collection->isEmpty()) {
                return;
            }

            yield from $collection->items;

            if (count($collection->items) < $current->pageSize) {
                return;
            }

            ++$page;
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return Collection<T>
     */
    private function mapPage(array $data, CollectionRequestOptions $opts): Collection
    {
        /** @var list<T> $items */
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = $this->mapItem(static::getItemClass(), $row);
        }

        return new Collection(
            $items,
            $opts->page,
            $opts->pageSize,
            count($items),
        );
    }
}
