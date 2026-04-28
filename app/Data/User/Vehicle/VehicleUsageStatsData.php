<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Agrégat statistiques d'utilisation d'un véhicule pour l'année active —
 * affiché dans les KPI cards + le breakdown de la page Show.
 *
 *   - actualTaxThisYear  : ce qui est effectivement dû compte tenu des
 *                          attributions réelles (somme par couple,
 *                          arrondie par couple — vue informative).
 *   - fullYearTax        : maximum théorique annuel (1 véhicule
 *                          attribué 100 % à 1 entreprise, sans LCD).
 *   - dailyTaxRate       : `fullYearTax / daysInYear`.
 *   - companies          : 1 entrée par entreprise utilisatrice,
 *                          triée par jours décroissants.
 */
#[TypeScript]
final class VehicleUsageStatsData extends Data
{
    /**
     * @param  list<VehicleCompanyUsageData>  $companies
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
    ) {}
}
