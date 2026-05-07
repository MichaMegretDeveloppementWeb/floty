<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Comparaison KPI vs année précédente · sous-objet de
 * {@see DashboardKpiData} (chantier η Phase 4, enrichi Phase 14.W).
 *
 * **Sémantique double** : pour les 4 KPIs cumulatifs (jours, contrats,
 * taxes, occupation), la comparaison porte sur la même fenêtre YTD Y-1
 * (du 1er janvier Y-1 au même jour-mois). Pour les recettes locatives,
 * la comparaison porte sur l'année calendaire complète Y-1 (jan-déc),
 * cohérent avec la sémantique full year de {@see DashboardKpiData::$recettesLocativesCents}.
 *
 * Le `delta*Percent` est calculé côté backend pour éviter de dupliquer
 * la logique côté front.
 *
 * Pour le taux d'occupation, le delta est en **points de pourcentage**
 * (« +3 pt »), pas en % relatif (qui n'aurait pas de sens : passer de
 * 50 % à 53 % n'est pas « +6 % », c'est « +3 pt »).
 */
#[TypeScript]
final class DashboardKpiComparisonData extends Data
{
    public function __construct(
        /** Année comparée (Y-1). */
        public int $year,
        /** Date de fin de la fenêtre comparée (Y-1, même jour-mois que aujourd'hui). */
        public string $endDate,
        public int $joursVehicule,
        /**
         * Total contrats ayant une activité sur la période Y-1 YTD.
         * Pas de sous-décompte « actifs » ici · la notion « actif au
         * 5 mai 2025 » est une photographie ponctuelle qui ne se
         * compare pas pertinemment d'une année à l'autre.
         */
        public int $contracts,
        public float $taxesDues,
        public float $tauxOccupation,
        /** Recettes locatives Y-1 plein année (jan-déc). */
        public int $recettesLocativesCents,
        /** Variation relative en % pour les 3 KPIs cumulatifs (jours, contrats, taxes). Null si Y-1 = 0. */
        public ?float $deltaJoursVehiculePercent,
        public ?float $deltaContractsPercent,
        public ?float $deltaTaxesDuesPercent,
        /** Variation absolue en points de pourcentage pour le taux d'occupation. */
        public float $deltaTauxOccupationPoints,
        /** Variation relative % recettes locatives Y vs Y-1. Null si Y-1 = 0. */
        public ?float $deltaRecettesLocativesPercent,
    ) {}
}
