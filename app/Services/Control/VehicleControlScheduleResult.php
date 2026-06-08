<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Enums\Control\ControlScheduleStatus;
use Carbon\CarbonImmutable;

/**
 * Lightweight result of resolving one ACTIVE regulatory control's échéance for
 * a vehicle during a fleet-wide schedule scan: its name, computed next due
 * date, and derived schedule status. Schedule-only (no recipient cascade).
 */
final readonly class VehicleControlScheduleResult
{
    public function __construct(
        public string $controlName,
        public CarbonImmutable $nextDue,
        public ControlScheduleStatus $scheduleStatus,
    ) {}
}
