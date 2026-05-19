<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Data\User\Vehicle\ConflictingContractData;
use App\Data\User\Vehicle\ConflictingUnavailabilityData;
use App\Data\User\Vehicle\VehicleExitImpactData;
use App\Models\Contract;
use App\Models\Unavailability;

/**
 * Computes active contracts and unavailabilities that would overflow a
 * proposed vehicle exit date (ADR-0018 § 8.1).
 */
final readonly class VehicleExitImpactComputer
{
    public function __construct(
        private ContractReadRepositoryInterface $contracts,
        private UnavailabilityReadRepositoryInterface $unavailabilities,
    ) {}

    /**
     * A conflict is any contract or unavailability whose end is strictly
     * after `exitDate`. A contract ending exactly at `exitDate` is not a
     * conflict (vehicle remains usable through exit_date inclusive).
     */
    public function computeImpact(int $vehicleId, string $exitDate): VehicleExitImpactData
    {
        $contracts = $this->contracts->findAllOverlapping(
            $vehicleId,
            $exitDate,
            '9999-12-31',
        )->filter(static fn (Contract $c): bool => $c->end_date->greaterThan($exitDate))
            ->values();

        $unavailabilities = $this->unavailabilities->findActiveOverlappingDateForVehicle(
            $vehicleId,
            $exitDate,
        );

        $contractData = $contracts
            ->map(static fn (Contract $c): ConflictingContractData => new ConflictingContractData(
                id: $c->id,
                companyShortCode: $c->company->short_code,
                startDate: $c->start_date->toDateString(),
                endDate: $c->end_date->toDateString(),
            ))
            ->values()
            ->all();

        $unavailabilityData = $unavailabilities
            ->map(static fn (Unavailability $u): ConflictingUnavailabilityData => new ConflictingUnavailabilityData(
                id: $u->id,
                type: $u->type,
                startDate: $u->start_date->toDateString(),
                endDate: $u->end_date?->toDateString() ?? '9999-12-31',
            ))
            ->values()
            ->all();

        return new VehicleExitImpactData(
            hasConflicts: $contracts->isNotEmpty() || $unavailabilities->isNotEmpty(),
            conflictingContracts: $contractData,
            conflictingUnavailabilities: $unavailabilityData,
        );
    }
}
