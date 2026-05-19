<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Year-to-date fiscal KPIs of the Dashboard "Présent" panel. All four
 * dimensions cumulate from 1st January to today.
 *
 * Rental revenue is exposed independently through {@see DashboardKpiRecettesData}
 * served as a distinct `Inertia::defer` group.
 */
#[TypeScript]
final class DashboardKpiData extends Data
{
    public function __construct(
        /** Current calendar year (fixed; not the selector value). */
        public int $year,
        /** Vehicle-days occupied from 1st January to today. */
        public int $joursVehicule,
        /**
         * Contracts whose range overlaps `[1st January, today]`. Includes
         * contracts closed during the year and contracts still ongoing.
         */
        public int $contracts,
        /**
         * Subset of `contracts` still active today (today ∈ `[start, end]`).
         * Displayed as a sub-line of the "Contrats" KPI.
         */
        public int $contractsActiveNow,
        /** YTD taxes due (CO₂ + pollutants, all companies). */
        public float $taxesDues,
        /**
         * Fleet utilization rate = realized vehicle-days / theoretical
         * vehicle-days available since 1st January. Percentage in [0, 100],
         * rounded to one decimal.
         */
        public float $tauxOccupation,
    ) {}
}
