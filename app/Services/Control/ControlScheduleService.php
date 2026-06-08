<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Enums\Control\ControlScheduleStatus;
use App\Enums\Control\DurationUnit;
use Carbon\CarbonImmutable;

/**
 * Pure échéance engine for regulatory controls (Chantier B / B2). No DB, no
 * ambient clock: "today" is always injected so the logic is fully testable.
 *
 * Next due date:
 *   - never executed : anchor + initial duration ;
 *   - already executed : last execution date + cycle (the recorded date becomes
 *     the new reference, retroactive entry allowed).
 *
 * The legal anchor of the contrôle technique (first registration date) is
 * carried by {@see App\Enums\Control\ControlAnchor}; the caller reads the matching
 * `vehicles.*` column and passes it here.
 */
final class ControlScheduleService
{
    /**
     * An execution recorded within this many days is surfaced as "done recently".
     */
    private const int DONE_RECENTLY_WINDOW_DAYS = 30;

    public function nextDueDate(
        CarbonImmutable $anchorDate,
        int $initialValue,
        DurationUnit $initialUnit,
        int $cycleValue,
        DurationUnit $cycleUnit,
        ?CarbonImmutable $lastExecutionDate,
    ): CarbonImmutable {
        if ($lastExecutionDate !== null) {
            return $this->addDuration($lastExecutionDate, $cycleValue, $cycleUnit);
        }

        return $this->addDuration($anchorDate, $initialValue, $initialUnit);
    }

    public function deriveStatus(
        CarbonImmutable $nextDue,
        ?CarbonImmutable $lastExecutionDate,
        CarbonImmutable $today,
        int $daysBefore,
        ?CarbonImmutable $vehicleExitDate,
    ): ControlScheduleStatus {
        // ADR-0018: once the vehicle has left the fleet, an échéance on or after
        // the exit no longer applies (the exit day itself belongs to the new
        // owner, so a control due that day is not the fleet's to do).
        if ($vehicleExitDate !== null && $nextDue->greaterThanOrEqualTo($vehicleExitDate)) {
            return ControlScheduleStatus::NotApplicable;
        }

        if ($today->greaterThan($nextDue)) {
            return ControlScheduleStatus::Overdue;
        }

        // The échéance is exactly today: due now, not merely "proche".
        if ($today->isSameDay($nextDue)) {
            return ControlScheduleStatus::DueToday;
        }

        if ($today->greaterThanOrEqualTo($nextDue->subDays($daysBefore))) {
            return ControlScheduleStatus::DueSoon;
        }

        if (
            $lastExecutionDate !== null
            && $lastExecutionDate->greaterThanOrEqualTo($today->subDays(self::DONE_RECENTLY_WINDOW_DAYS))
        ) {
            return ControlScheduleStatus::DoneRecently;
        }

        return ControlScheduleStatus::Upcoming;
    }

    private function addDuration(CarbonImmutable $date, int $value, DurationUnit $unit): CarbonImmutable
    {
        return match ($unit) {
            DurationUnit::Years => $date->addYears($value),
            DurationUnit::Months => $date->addMonths($value),
        };
    }
}
