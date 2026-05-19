<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response of `GET /app/planning/week`: detail of one week for a given
 * vehicle, consumed by the planning drawer.
 */
#[TypeScript]
final class PlanningWeekData extends Data
{
    /**
     * @param  list<WeekDaySlotData>  $days
     * @param  list<WeekCompanyPresenceData>  $companiesOnWeek
     * @param  list<string>  $vehicleBusyDates  ISO Y-m-d dates in the fiscal year where the vehicle already holds an active contract; feeds the `disabled-dates` of the DateRangePicker to block conflicting selections outside the displayed week.
     */
    public function __construct(
        public int $weekNumber,
        public string $weekStart,
        public string $weekEnd,
        public int $vehicleId,
        public string $licensePlate,
        public array $days,
        public array $companiesOnWeek,
        public array $vehicleBusyDates,
    ) {}
}
