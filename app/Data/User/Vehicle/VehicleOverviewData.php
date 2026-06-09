<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Overview-tab-only payload for the Vehicle Show page · the current state
 * (ongoing event/rental today), the heavy usage aggregation (52-week timeline
 * + per-company breakdown), the current-year KPI, the busy-dates for the
 * unavailability modal and the multi-year history. Served eagerly only when
 * `?tab=overview` (tab-gating); the
 * other tabs do not pay this when loaded directly. The slim identity/base
 * lives on {@see VehicleData}, always eager (header + every tab + Edit).
 *
 * `history` was previously a separate `Inertia::defer` prop · folded here
 * because defer fetches on every tab's initial visit, leaking the
 * overview history onto non-overview tabs.
 */
#[TypeScript]
final class VehicleOverviewData extends Data
{
    /**
     * @param  list<string>  $busyDates  Dates ISO Y-m-d assigned on the active year (unavailability modal).
     * @param  list<VehicleYearStatsData>  $history  Past fiscal years, newest first.
     */
    public function __construct(
        public CurrentVehicleStatusData $currentStatus,
        public VehicleUsageStatsData $usageStats,
        public VehicleYearStatsData $kpiStats,
        public bool $kpiFiscalAvailable,
        public array $busyDates,
        #[DataCollectionOf(VehicleYearStatsData::class)]
        public array $history,
    ) {}
}
