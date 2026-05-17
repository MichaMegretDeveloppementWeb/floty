<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * KPIs fiscaux « Présent » du Dashboard · 4 indicateurs YTD de l'année
 * en cours avec comparaison Y-1 (chantier η Phase 4).
 *
 * **Sémantique YTD** · les 4 dimensions sont cumulatives du 1er janvier
 * au jour courant. La comparaison Y-1 porte sur la même fenêtre YTD Y-1
 * (du 1er janvier Y-1 au même jour-mois).
 *
 * `previousYearComparison` est `null` quand on n'a pas de données
 * exploitables sur l'année précédente (typiquement · première année
 * d'utilisation de l'app).
 *
 * **Carte recettes séparée** · les recettes locatives sont chargées
 * indépendamment via {@see DashboardKpiRecettesData} en `Inertia::defer`
 * distinct (chantier perf Dashboard 2026-05-17) · ~60 queries SQL
 * hors chemin critique.
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
        /** Comparaison vs Y-1 YTD à même jour-mois, ou null si Y-1 vide. */
        public ?DashboardKpiComparisonData $previousYearComparison,
    ) {}
}
