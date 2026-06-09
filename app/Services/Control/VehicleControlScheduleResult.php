<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Enums\Control\ControlScheduleStatus;
use Carbon\CarbonImmutable;

/**
 * Lightweight result of resolving one ACTIVE regulatory control's échéance for
 * a vehicle during a fleet-wide schedule scan: its name, computed next due
 * date, the date from which it starts needing attention, and derived schedule
 * status. Schedule-only (no recipient cascade).
 */
final readonly class VehicleControlScheduleResult
{
    public function __construct(
        public string $controlName,
        public CarbonImmutable $nextDue,
        /**
         * Date from which the control starts needing attention, i.e.
         * `nextDue` minus the EFFECTIVE reminder window (per-control:
         * override → definition → global default). A control is "due"
         * (Overdue, DueToday or DueSoon) exactly when `today >= dueFrom`,
         * mirroring {@see ControlScheduleService::deriveStatus()}. Stable
         * (no "today"): it feeds the materialised `vehicles.controls_due_from`.
         */
        public CarbonImmutable $dueFrom,
        public ControlScheduleStatus $scheduleStatus,
    ) {}
}
