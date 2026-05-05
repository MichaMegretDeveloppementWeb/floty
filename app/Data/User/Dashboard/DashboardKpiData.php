<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * KPIs « Présent » du Dashboard — 4 indicateurs clés de l'année en
 * cours, avec comparaison à la même période de l'année précédente
 * (chantier η Phase 4).
 *
 * Les 4 dimensions pivots couvrent : utilisation flotte
 * (`joursVehicule`), activité commerciale (`contractsActifs`),
 * fiscalité (`taxesDues`), santé business (`tauxOccupation`).
 *
 * `previousYearComparison` est `null` quand on n'a pas de données
 * exploitables sur l'année précédente (typiquement : première année
 * d'utilisation de l'app). Sinon il porte les 4 mêmes KPIs calculés sur
 * la même période Y-1 (du 1er janvier Y-1 au même jour-mois Y-1) pour
 * permettre une comparaison honnête à mi-année.
 */
#[TypeScript]
final class DashboardKpiData extends Data
{
    public function __construct(
        /** Année calendaire courante (figée, ≠ sélecteur). */
        public int $year,
        /** Jours-véhicule occupés du 1er janvier au jour courant. */
        public int $joursVehicule,
        /** Contrats actuellement actifs (date courante ∈ [start, end]). */
        public int $contractsActifs,
        /** Taxes dues YTD (CO₂ + polluants, toutes entreprises). */
        public float $taxesDues,
        /**
         * Taux d'occupation flotte = jours-véhicule réalisés / jours-véhicule
         * théoriques disponibles depuis le 1er janvier. En pourcentage entre
         * 0 et 100, arrondi à 1 décimale.
         */
        public float $tauxOccupation,
        /** Comparaison vs même période Y-1, ou null si Y-1 vide. */
        public ?DashboardKpiComparisonData $previousYearComparison,
    ) {}
}
