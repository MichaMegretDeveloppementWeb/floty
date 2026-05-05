<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Comparaison KPI vs même période année précédente — sous-objet de
 * {@see DashboardKpiData} (chantier η Phase 4).
 *
 * Chaque champ porte la valeur calculée sur la même période Y-1 (du
 * 1er janvier Y-1 au même jour-mois Y-1, donc une fenêtre comparable).
 * Le `delta*Percent` est calculé côté backend pour éviter de
 * dupliquer la logique côté front.
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
        public int $contractsActifs,
        public float $taxesDues,
        public float $tauxOccupation,
        /** Variation relative en % pour les 3 KPIs cumulatifs (jours, contrats, taxes). Null si Y-1 = 0. */
        public ?float $deltaJoursVehiculePercent,
        public ?float $deltaContractsActifsPercent,
        public ?float $deltaTaxesDuesPercent,
        /** Variation absolue en points de pourcentage pour le taux d'occupation. */
        public float $deltaTauxOccupationPoints,
    ) {}
}
