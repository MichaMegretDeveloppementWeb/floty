<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Enums\Control\ControlScheduleStatus;
use Carbon\CarbonImmutable;

/**
 * One active control's resolved échéance for a vehicle during a fleet scan:
 * name, next due date, due-from date and schedule status.
 */
final readonly class VehicleControlScheduleResult
{
    public function __construct(
        public string $controlName,
        public CarbonImmutable $nextDue,
        /** `nextDue` minus the effective reminder window; feeds `vehicles.controls_due_from`. */
        public CarbonImmutable $dueFrom,
        public ControlScheduleStatus $scheduleStatus,
    ) {}
}
