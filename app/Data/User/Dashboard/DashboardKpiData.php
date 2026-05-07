<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * KPIs « Présent » du Dashboard · 5 indicateurs de l'année en cours
 * avec comparaison à l'année précédente (chantier η Phase 4, enrichi
 * Phase 14.W avec `recettesLocativesCents`).
 *
 * **Sémantique temporelle** : 4 dimensions sont YTD (du 1er janvier au
 * jour courant) : `joursVehicule`, `contracts`, `taxesDues`,
 * `tauxOccupation`. Une dimension est **full year** (recettes
 * locatives) : la facturation prévue sur toute l'année calendaire,
 * mois passés + mois futurs. Cette asymétrie est volontaire : pour
 * une société de location, le CA annuel attendu est plus parlant que
 * le CA cumulé YTD (qui serait toujours sous-estimé en début
 * d'année). Le caption frontend lève l'ambiguïté.
 *
 * `previousYearComparison` est `null` quand on n'a pas de données
 * exploitables sur l'année précédente (typiquement : première année
 * d'utilisation de l'app). Sinon il porte les KPIs calculés sur la
 * même fenêtre Y-1 (YTD à même jour-mois pour les 4 cumulatives,
 * full year pour les recettes).
 */
#[TypeScript]
final class DashboardKpiData extends Data
{
    public function __construct(
        /** Année calendaire courante (figée, ≠ sélecteur). */
        public int $year,
        /** Jours-véhicule occupés du 1er janvier au jour courant. */
        public int $joursVehicule,
        /**
         * Nombre total de contrats ayant une activité sur la période YTD
         * (= dont la plage `[start, end]` chevauche `[1er janvier, aujourd'hui]`).
         * Inclut les contrats clos courant l'année + ceux encore actifs.
         */
        public int $contracts,
        /**
         * Sous-décompte des contrats encore en cours aujourd'hui
         * (date courante ∈ `[start, end]`). Affiché en sous-titre du KPI
         * Contrats. Présent uniquement sur la lentille Présent · pas
         * dans la comparaison Y-1 (la notion « actif au 5 mai 2025 »
         * n'est pas exploitable, on ne compare que les totaux).
         */
        public int $contractsActiveNow,
        /** Taxes dues YTD (CO₂ + polluants, toutes entreprises). */
        public float $taxesDues,
        /**
         * Taux d'occupation flotte = jours-véhicule réalisés / jours-véhicule
         * théoriques disponibles depuis le 1er janvier. En pourcentage entre
         * 0 et 100, arrondi à 1 décimale. Affiché en sous-ligne discrète
         * sur la carte « Jours-véhicule occupés » (numérateur + ratio).
         */
        public float $tauxOccupation,
        /**
         * Recettes locatives HT **plein année** (toutes entreprises × tous
         * mois 1..12). Calcul via `BillingBreakdownService` en mode partiel
         * (mois sans tarif annuel exclus). Indépendant de l'émission des
         * factures : reflète le CA contractuel, pas le facturé.
         */
        public int $recettesLocativesCents,
        /** Comparaison vs Y-1 (YTD pour cumulatives, full year pour recettes), ou null si Y-1 vide. */
        public ?DashboardKpiComparisonData $previousYearComparison,
    ) {}
}
