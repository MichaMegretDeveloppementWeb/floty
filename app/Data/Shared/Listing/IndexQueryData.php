<?php

declare(strict_types=1);

namespace App\Data\Shared\Listing;

use Spatie\LaravelData\Data;

/**
 * DTO d'entrée commun à toutes les pages Index server-side (cf. ADR-0020).
 *
 * Factorise les paramètres `{page, perPage, search, sortKey, sortDirection}`.
 * Les sous-classes concrètes ajoutent leurs filtres spécifiques au domaine
 * et déclarent leur whitelist de `sortKey` via la méthode statique
 * abstraite `allowedSortKeys()`.
 *
 * Validation :
 *  - `page` : entier ≥ 1 (défaut 1)
 *  - `perPage` : entier IN (10, 20, 50, 100) (défaut 20)
 *  - `search` : string ou null
 *  - `sortKey` : string IN `static::allowedSortKeys()` ou null
 *  - `sortDirection` : enum SortDirection (défaut Asc)
 *
 * Sécurité : la whitelist `sortKey` empêche toute injection SQL via
 * `orderBy($_GET['sortKey'])`.
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
        // Normalisation : empty string search → null (cohérence URL `?search=`)
        if ($this->search === '') {
            $this->search = null;
        }
    }

    /**
     * Liste des clés de tri autorisées. Les sous-classes doivent
     * implémenter cette méthode pour exposer les colonnes triables.
     *
     * @return array<int, string>
     */
    abstract public static function allowedSortKeys(): array;

    /**
     * Règles de validation Spatie Data. La whitelist `sortKey` est
     * dynamique via late static binding sur `allowedSortKeys()`.
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
