<?php

declare(strict_types=1);

namespace App\Data\Shared\Listing;

use Spatie\LaravelData\Data;

/**
 * Shared input DTO for every server-side Index page (ADR-0020).
 *
 * Factors out `{page, perPage, search, sortKey, sortDirection}`. Concrete
 * subclasses add their domain-specific filters and declare their sortKey
 * whitelist via the abstract static `allowedSortKeys()`. That whitelist
 * is also what prevents SQL injection via `orderBy($_GET['sortKey'])`.
 */
abstract class IndexQueryData extends Data
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const DEFAULT_PER_PAGE = 20;

    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public ?string $search = null,
        public ?string $sortKey = null,
        public SortDirection $sortDirection = SortDirection::Asc,
    ) {
        if ($this->search === '') {
            $this->search = null;
        }
    }

    /**
     * Whitelist of sort keys exposed by the concrete subclass.
     *
     * @return array<int, string>
     */
    abstract public static function allowedSortKeys(): array;

    /**
     * Spatie Data validation rules. The `sortKey` whitelist binds to the
     * subclass via late static binding on `allowedSortKeys()`.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
            'perPage' => ['integer', 'in:'.implode(',', self::PER_PAGE_OPTIONS)],
            'search' => ['nullable', 'string', 'max:255'],
            'sortKey' => ['nullable', 'string', 'in:'.implode(',', static::allowedSortKeys())],
            'sortDirection' => ['nullable', 'in:asc,desc'],
        ];
    }
}
