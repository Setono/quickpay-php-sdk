<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request;

use Webmozart\Assert\Assert;

/**
 * Immutable options for a paginated list request: which page and how many entries per page.
 *
 * Quickpay paginates list endpoints with the `page` and `page_size` query parameters and returns a
 * bare JSON array with no total-count headers, so {@see toArray()} maps directly to those two query
 * parameters.
 */
final class CollectionRequestOptions
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $pageSize = 20,
    ) {
        Assert::greaterThanEq($page, 1);
        Assert::greaterThanEq($pageSize, 1);
    }

    public static function new(): self
    {
        return new self();
    }

    public function withPage(int $page): self
    {
        return new self($page, $this->pageSize);
    }

    public function withPageSize(int $pageSize): self
    {
        return new self($this->page, $pageSize);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'page_size' => $this->pageSize,
        ];
    }
}
