<?php

declare(strict_types=1);

namespace App\Data\Shared\Listing;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Pagination metadata returned by server-side Index pages (ADR-0020).
 *
 * Mirrors Laravel's `LengthAwarePaginator::toArray()['meta']` shape.
 * `from` and `to` are nullable when the page is empty.
 */
#[TypeScript]
final class PaginationMetaData extends Data
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        public ?int $from,
        public ?int $to,
    ) {}

    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            from: $paginator->firstItem(),
            to: $paginator->lastItem(),
        );
    }
}
