<?php

declare(strict_types=1);

namespace App\Data\Shared\Listing;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Méta-données de pagination renvoyées par les Index server-side
 * (cf. ADR-0020). Reflète la shape `LengthAwarePaginator::toArray()['meta']`
 * de Laravel, exposée en TypeScript pour le composant `Paginator.vue`.
 *
 * `from` et `to` sont nullables : ils valent `null` quand la page est vide
 * (ex: page 1 sur dataset 0).
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
