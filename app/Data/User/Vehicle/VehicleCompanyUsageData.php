<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * 1 ligne du tableau « Répartition fiscale par entreprise utilisatrice »
 * affiché sur la page Show d'un véhicule pour l'année active.
 *
 *   - daysUsed       : jours d'attribution du véhicule à cette entreprise
 *   - proratoPercent : pourcentage `daysUsed / daysInYear × 100` (1 décimale)
 *   - taxCo2         : tarif annuel CO₂ × prorata, arrondi à 2 décimales
 *   - taxPollutants  : tarif annuel polluants × prorata, arrondi à 2 décimales
 *   - taxTotal       : somme `taxCo2 + taxPollutants`, arrondie à 2 décimales
 */
#[TypeScript]
final class VehicleCompanyUsageData extends Data
{
    public function __construct(
        public int $companyId,
        public string $shortCode,
        public string $legalName,
        public CompanyColor $color,
        public int $daysUsed,
        public float $proratoPercent,
        public float $taxCo2,
        public float $taxPollutants,
        public float $taxTotal,
    ) {}
}
