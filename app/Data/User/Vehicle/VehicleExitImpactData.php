<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Impact summary of a proposed vehicle exit: active contracts and unavailabilities
 * that overlap the exit date (ADR-0018).
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
