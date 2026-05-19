<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DTO\Fiscal\ContractsByPair;
use App\Models\Unavailability;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Per-request memory cache for a Dashboard computation over a year
 * range.
 *
 * Pre-loads in bulk · contracts grouped by `(vehicle, company)` for
 * every year in scope, the relevant vehicles (single query), the
 * unavailabilities per vehicle (single query).
 *
 * Rental revenues are not precomputed here; they live in a dedicated
 * `Inertia::defer` group via
 * {@see DashboardStatsService::computeKpisRecettes()}, batched by
 * {@see App\Services\Billing\BillingBreakdownService::totalRecettesForYears}
 * (3 total SQL queries for N companies × M years).
 */
final readonly class DashboardScopeContext
{
    /**
     * @param  array<int, ContractsByPair>  $contractsByYear  Contracts pivot per scope year
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexed vehicles (superset over the scope)
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId  Unavailabilities per vehicle (superset)
     */
    public function __construct(
        public array $contractsByYear,
        public Collection $vehiclesById,
        public array $unavailabilitiesByVehicleId,
    ) {}

    /**
     * Pivot for a scope year. Returns an empty pivot when the year is
     * not in scope (mirrors the standalone `loadContractsByPair`
     * behaviour, which does not crash on a year without contracts).
     */
    public function contractsForYear(int $year): ContractsByPair
    {
        return $this->contractsByYear[$year] ?? new ContractsByPair([]);
    }
}
