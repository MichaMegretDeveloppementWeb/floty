<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Agrégat statistiques d'utilisation d'un véhicule pour l'année active —
 * affiché dans les KPI cards + la timeline 52 semaines + le tableau
 * breakdown sur la page Show.
 *
 *   - actualTaxThisYear  : ce qui est effectivement dû compte tenu des
 *                          attributions réelles (somme par couple,
 *                          arrondie par couple — vue informative).
 *   - fullYearTax        : maximum théorique annuel (1 véhicule
 *                          attribué 100 % à 1 entreprise, sans LCD).
 *   - dailyTaxRate       : `fullYearTax / daysInYear`.
 *   - companies          : 1 entrée par entreprise utilisatrice (avec
 *                          détail co2/polluants/total), triée par
 *                          jours décroissants.
 *   - weeklyBreakdown    : 1 entrée par semaine ISO de l'année (52-53
 *                          entrées, semaines vides incluses) pour la
 *                          timeline visuelle.
 */
#[TypeScript]
final class VehicleUsageStatsData extends Data
{
    /**
     * @param  list<VehicleCompanyUsageData>  $companies
     * @param  list<VehicleWeekUsageData>  $weeklyBreakdown
     * @param  list<int>  $unavailabilityWeeks  Semaines ISO contenant ≥ 1 jour d'indispo
     */
    public function __construct(
        public int $fiscalYear,
        public int $daysInYear,
        public int $daysUsedThisYear,
        public float $actualTaxThisYear,
        public float $fullYearTax,
        public float $dailyTaxRate,
        #[DataCollectionOf(VehicleCompanyUsageData::class)]
        public array $companies,
        #[DataCollectionOf(VehicleWeekUsageData::class)]
        public array $weeklyBreakdown,
        public VehicleFullYearTaxBreakdownData $fullYearTaxBreakdown,
        public array $unavailabilityWeeks,
    ) {}
}
