<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Résultat du calcul d'impact d'une sortie de flotte proposée :
 * énumère les contrats et indisponibilités actifs qui débordent la
 * date de sortie.
 *
 * Si `hasConflicts === true`, l'Action {@see App\Actions\Vehicle\ExitVehicleAction}
 * lèvera {@see App\Exceptions\Vehicle\VehicleExitBlockedByConflictsException}
 * et la modale Sortie affichera la liste à l'utilisateur.
 *
 * Cf. ADR-0018 § 8.1 — section "Conflits détectés".
 */
#[TypeScript]
final class VehicleExitImpactData extends Data
{
    /**
     * @param  list<ConflictingContractData>  $conflictingContracts
     * @param  list<ConflictingUnavailabilityData>  $conflictingUnavailabilities
     */
    public function __construct(
        public bool $hasConflicts,
        #[DataCollectionOf(ConflictingContractData::class)]
        public array $conflictingContracts,
        #[DataCollectionOf(ConflictingUnavailabilityData::class)]
        public array $conflictingUnavailabilities,
    ) {}
}
