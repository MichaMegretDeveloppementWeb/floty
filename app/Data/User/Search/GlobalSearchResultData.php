<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Résultat de la recherche globale ⌘K (V1.1) · 5 groupes en parallèle
 * dont 2 conditionnels (`contractShortcuts`, `declarations`).
 *
 * Cf. {@see GlobalSearchService::searchAll} pour la logique d'activation
 * des groupes conditionnels.
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
     * Helper pour créer un résultat vide (5 groupes vides). Nommé
     * `emptyResult` et pas `empty` pour ne pas surcharger la méthode
     * statique homonyme exposée par {@see Data} (qui a une signature
     * différente · `empty(array, ...)` côté Spatie).
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
