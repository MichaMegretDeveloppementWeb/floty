<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Read-only view of a contract that belongs to a risk cluster. Exposes
 * the dates, the days within the target year, and the gap to the
 * previous contract of the cluster, all of which the human reviewer
 * needs for arbitration.
 */
#[TypeScript]
final class ClusterContractData extends Data
{
    public function __construct(
        public int $contractId,
        public int $vehicleId,
        public string $vehiclePlate,
        /** ISO 8601 (Y-m-d). */
        public string $startDate,
        /** ISO 8601 (Y-m-d). */
        public string $endDate,
        /** Days of this contract within the target year (may be < total when straddling). */
        public int $durationDaysInYear,
        /** Full days between this contract and the previous one of the cluster; null for the first. */
        public ?int $intervalBeforeDays,
    ) {}

    public static function fromContract(
        Contract $contract,
        int $year,
        ?Contract $previous,
    ): self {
        $durationDaysInYear = $contract->countDaysInYear($year);

        $interval = null;
        if ($previous !== null) {
            $interval = (int) $previous->end_date
                ->copy()
                ->diffInDays($contract->start_date) - 1;
        }

        return new self(
            contractId: $contract->id,
            vehicleId: $contract->vehicle_id,
            vehiclePlate: $contract->vehicle->license_plate,
            startDate: $contract->start_date->toDateString(),
            endDate: $contract->end_date->toDateString(),
            durationDaysInYear: $durationDaysInYear,
            intervalBeforeDays: $interval,
        );
    }
}
