<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * KPIs fiscaux « Présent » du Dashboard · 4 indicateurs YTD de l'année
 * en cours.
 *
 * **Sémantique YTD** · les 4 dimensions sont cumulatives du 1er janvier
 * au jour courant.
 *
 * **Comparaison Y-1 supprimée** (chantier perf Dashboard 2026-05-17 v3) ·
 * l'historique multi-années (`DashboardYearHistoryData`, désormais chargé
 * à la demande via un bouton) sert de support visuel à la comparaison
 * temporelle, plus besoin d'un trend Y-1 par carte. Gain CPU · le
 * pipeline fiscal n'est plus exécuté 2× (year + year-1) au mount Dashboard.
 *
 * **Carte recettes séparée** · les recettes locatives sont chargées
 * indépendamment via {@see DashboardKpiRecettesData} en `Inertia::defer`
 * distinct (chantier perf Dashboard 2026-05-17).
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
         * Contrats.
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
    ) {}
}
