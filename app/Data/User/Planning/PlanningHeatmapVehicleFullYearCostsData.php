<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Theoretical full-year fiscal costs of a vehicle, served as the "fast"
 * `Inertia::defer` group on Planning pages.
 *
 * Kept separate from the real-usage tax (`PlanningHeatmapVehicleRealCostsData`,
 * roughly 3-5 ms per vehicle) so the left-side "Taxe pleine" cell appears
 * very quickly, independently of the slower "€XXXX · N j" cell on the
 * right. These values are 100% theoretical and identical between the
 * Vue d'ensemble and Vue Entreprise.
 */
#[TypeScript]
final class PlanningHeatmapVehicleFullYearCostsData extends Data
{
    public function __construct(
        /** Theoretical full-year tax (€) at 100% utilisation. */
        public float $fullYearTax,
        /** Daily prorata = `fullYearTax / daysInYear` (€/day). */
        public float $dailyTaxRate,
    ) {}
}
