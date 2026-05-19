<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Global search result: 5 parallel groups, 2 of which are conditional
 * (`contractShortcuts`, `declarations`). See `GlobalSearchService::searchAll`
 * for the activation logic.
 */
#[TypeScript]
final class GlobalSearchResultData extends Data
{
    /**
     * @param  list<GlobalSearchVehicleItemData>  $vehicles
     * @param  list<GlobalSearchCompanyItemData>  $companies
     * @param  list<GlobalSearchDriverItemData>  $drivers
     * @param  list<GlobalSearchContractShortcutData>  $contractShortcuts
     * @param  list<GlobalSearchDeclarationItemData>  $declarations
     */
    public function __construct(
        public string $query,
        public array $vehicles,
        public array $companies,
        public array $drivers,
        public array $contractShortcuts,
        public array $declarations,
    ) {}

    /**
     * Build an empty result (all five groups empty). Named `emptyResult`
     * rather than `empty` to avoid colliding with Spatie's static
     * `empty(array, ...)`.
     */
    public static function emptyResult(string $query): self
    {
        return new self(
            query: $query,
            vehicles: [],
            companies: [],
            drivers: [],
            contractShortcuts: [],
            declarations: [],
        );
    }
}
