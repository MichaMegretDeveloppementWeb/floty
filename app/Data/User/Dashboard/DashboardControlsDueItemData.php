<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use App\Enums\Control\ControlScheduleStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Dashboard "control due" item: one regulatory control of one active vehicle
 * whose échéance needs attention (overdue, due today, or within the reminder
 * window). `nextDueDate` is the computed échéance (ISO date) and `isOverdue`
 * flags a past échéance, for at-a-glance ranking and styling.
 */
#[TypeScript]
final class DashboardControlsDueItemData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public string $controlName,
        public string $nextDueDate,
        public ControlScheduleStatus $scheduleStatus,
        public bool $isOverdue,
    ) {}
}
