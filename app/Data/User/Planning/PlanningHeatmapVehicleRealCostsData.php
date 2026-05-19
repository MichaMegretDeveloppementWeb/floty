<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Real-usage fiscal cost of a vehicle, served as the "slow"
 * `Inertia::defer` group on Planning pages.
 *
 * `vehicleAnnualTax` is not cached (depends on contracts + unavailabilities
 * + scope; complex invalidation). Splitting it off the full-year DTO
 * keeps the heatmap from paying for this ~3-5 ms / vehicle work upfront
 * (~200 ms on a 64-vehicle fleet).
 *
 * Reflects the current scope: global tax in Vue d'ensemble, company-scoped
 * tax in Vue Entreprise (replaces the former `annualTaxDueForCompany`).
 */
#[TypeScript]
final class PlanningHeatmapVehicleRealCostsData extends Data
{
    public function __construct(
        /** Annual tax due (€) based on real usage for the current year. */
        public float $annualTaxDue,
    ) {}
}
