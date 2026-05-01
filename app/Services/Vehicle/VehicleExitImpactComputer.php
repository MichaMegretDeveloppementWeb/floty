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
 * Calcule la liste des contrats et indisponibilités actifs d'un
 * véhicule qui débordent une date de sortie de flotte proposée.
 *
 * Consommé par :
 *   - {@see App\Actions\Vehicle\ExitVehicleAction} pour bloquer la
 *     sortie en présence de conflits (lève
 *     {@see App\Exceptions\Vehicle\VehicleExitBlockedByConflictsException}).
 *   - La modale Sortie côté frontend (via un endpoint dédié si
 *     pré-vérification UX nécessaire — défini chantier E.4).
 *
 * Cf. ADR-0018 § 8.1.
 */
final readonly class VehicleExitImpactComputer
{
    public function __construct(
        private ContractReadRepositoryInterface $contracts,
        private UnavailabilityReadRepositoryInterface $unavailabilities,
    ) {}

    /**
     * Conflit = contrat ou indispo dont `end_date > exitDate`.
     *
     * Un contrat dont la fin est exactement à `exitDate` n'est pas en
     * conflit (le véhicule est utilisable jusqu'à exit_date inclus).
     */
    public function computeImpact(int $vehicleId, string $exitDate): VehicleExitImpactData
    {
        // Tous les contrats overlappants la "fenêtre" [exitDate+1 jour, far future].
        // En pratique : findAllOverlapping cherche tous les contrats overlappants
        // l'intervalle ; on simplifie en filtrant ceux qui débordent strictement.
        $contracts = $this->contracts->findAllOverlapping(
            $vehicleId,
            $exitDate,
            '9999-12-31', // borne supérieure technique
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
