<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de la table « Répartition fiscale par véhicule » de l'onglet
 * Fiscalité d'une entreprise (chantier N.2).
 *
 * Le couple unique est (vehicle × company × year) : 1 ligne par
 * véhicule utilisé par l'entreprise sur l'année sélectionnée. Les
 * montants sont calculés via le pipeline fiscal standard
 * (`FleetFiscalAggregator::vehicleAnnualTaxBreakdownByCompany` côté
 * Vehicle Show, mais ici on inverse l'angle : 1 entreprise × N véhicules).
 */
#[TypeScript]
final class CompanyVehicleFiscalRowData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public ?string $brand,
        public ?string $model,
        public int $daysUsed,
        public float $proratoPercent,
        public float $taxCo2,
        public float $taxPollutants,
        public float $taxTotal,
    ) {}
}
