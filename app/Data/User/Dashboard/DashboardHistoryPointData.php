<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un point de l'historique annuel · une dimension à la fois (chantier
 * perf Dashboard 2026-05-17 v4 · refonte lazy par onglet).
 *
 * Chacune des 4 dimensions du graphique Évolution (Jours-véhicule,
 * Locations, Taxes dues, Recettes locatives) est servie par une prop
 * Inertia distincte (`historyJoursVehicule`, `historyContracts`,
 * `historyTaxes`, `historyRecettes`). Chaque prop est un
 * `list<DashboardHistoryPointData>`.
 *
 * Seul le 1er onglet (Jours-véhicule, peu coûteux) est servi en
 * `Inertia::defer` au mount. Les 3 autres en `Inertia::optional` sont
 * hydratés à la demande sur clic d'onglet via
 * `router.reload({only: ['historyXxx']})`.
 */
#[TypeScript]
final class DashboardHistoryPointData extends Data
{
    public function __construct(
        public int $year,
        /** Vrai si l'année est l'année calendaire courante (donc partielle). */
        public bool $isCurrentYear,
        /**
         * Valeur de la dimension pour cette année · int pour
         * jours-véhicule / locations / recettes (cents), float pour
         * taxes dues (€). Côté TS, mapped en `number`.
         */
        public int|float $value,
    ) {}
}
