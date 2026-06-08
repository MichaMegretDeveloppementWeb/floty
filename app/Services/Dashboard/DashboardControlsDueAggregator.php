<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardControlsDueData;
use App\Data\User\Dashboard\DashboardControlsDueItemData;
use App\Enums\Control\ControlScheduleStatus;
use App\Services\Control\FleetControlScheduleScanner;
use Carbon\CarbonImmutable;

/**
 * Builds the Dashboard "Contrôles à échéance" panel: the regulatory controls
 * reaching échéance across the active fleet (overdue / due today / due soon),
 * ranked by urgency, capped to the top few with the exhaustive total count.
 *
 * The échéance resolution is delegated to {@see FleetControlScheduleScanner}
 * (shared with the fleet-list badges): computed in PHP with the same engine as
 * the vehicle tab and the cron, over a fixed, bounded set of batched queries.
 * The "due" set mirrors the vehicle-tab badge (Active controls in Overdue /
 * DueToday / DueSoon; out-of-fleet excluded), pinned by an équivalence test.
 */
final readonly class DashboardControlsDueAggregator
{
    private const TOP_ITEMS = 6;

    public function __construct(
        private VehicleReadRepositoryInterface $vehicles,
        private FleetControlScheduleScanner $scanner,
    ) {}

    public function aggregate(CarbonImmutable $today): DashboardControlsDueData
    {
        $vehicles = $this->vehicles->findActiveForReminderScan($today);

        if ($vehicles->isEmpty()) {
            return DashboardControlsDueData::none();
        }

        $resultsByVehicle = $this->scanner->scanForVehicles($vehicles, $today);

        /** @var list<DashboardControlsDueItemData> $items */
        $items = [];

        foreach ($vehicles as $vehicle) {
            foreach ($resultsByVehicle[$vehicle->id] ?? [] as $result) {
                if (! $this->needsAttention($result->scheduleStatus)) {
                    continue;
                }

                $items[] = new DashboardControlsDueItemData(
                    vehicleId: $vehicle->id,
                    licensePlate: (string) $vehicle->license_plate,
                    controlName: $result->controlName,
                    nextDueDate: $result->nextDue->toDateString(),
                    scheduleStatus: $result->scheduleStatus,
                    isOverdue: $result->scheduleStatus === ControlScheduleStatus::Overdue,
                );
            }
        }

        // Overdue first, then soonest échéance, then license plate for a stable
        // order across runs.
        usort($items, static function (DashboardControlsDueItemData $a, DashboardControlsDueItemData $b): int {
            if ($a->isOverdue !== $b->isOverdue) {
                return $a->isOverdue ? -1 : 1;
            }

            return [$a->nextDueDate, $a->licensePlate] <=> [$b->nextDueDate, $b->licensePlate];
        });

        return new DashboardControlsDueData(
            count: count($items),
            items: array_slice($items, 0, self::TOP_ITEMS),
        );
    }

    private function needsAttention(ControlScheduleStatus $status): bool
    {
        return $status === ControlScheduleStatus::Overdue
            || $status === ControlScheduleStatus::DueToday
            || $status === ControlScheduleStatus::DueSoon;
    }
}
