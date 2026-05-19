<?php

declare(strict_types=1);

namespace App\Exceptions\Contract;

use App\Exceptions\BaseAppException;
use Illuminate\Support\Carbon;

/**
 * Contract create/update overlaps another active contract on the same vehicle (ADR-0014 D5).
 *
 * The `contracts_no_overlap_*` MySQL trigger is the source of truth; this exception is the
 * defense-in-depth layer that produces an explicit French message before reaching the DB.
 */
final class ContractOverlapException extends BaseAppException
{
    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly int $vehicleId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $conflictingContractId,
        public readonly string $conflictingStartDate,
        public readonly string $conflictingEndDate,
    ) {
        parent::__construct($technicalMessage, $userMessage);
    }

    public static function fromConflict(
        int $vehicleId,
        string $startDate,
        string $endDate,
        int $conflictingContractId,
        string $conflictingStartDate,
        string $conflictingEndDate,
    ): self {
        $startFr = Carbon::parse($startDate)->format('d/m/Y');
        $endFr = Carbon::parse($endDate)->format('d/m/Y');
        $conflictStartFr = Carbon::parse($conflictingStartDate)->format('d/m/Y');
        $conflictEndFr = Carbon::parse($conflictingEndDate)->format('d/m/Y');

        return new self(
            technicalMessage: sprintf(
                'Contract on vehicle %d for period [%s, %s] overlaps existing contract %d [%s, %s]',
                $vehicleId,
                $startDate,
                $endDate,
                $conflictingContractId,
                $conflictingStartDate,
                $conflictingEndDate,
            ),
            userMessage: sprintf(
                'La plage de la location (%s → %s) chevauche une location existante '
                .'sur ce véhicule (du %s au %s). Ajustez les bornes ou supprimez '
                .'la location en conflit avant d\'enregistrer.',
                $startFr,
                $endFr,
                $conflictStartFr,
                $conflictEndFr,
            ),
            vehicleId: $vehicleId,
            startDate: $startDate,
            endDate: $endDate,
            conflictingContractId: $conflictingContractId,
            conflictingStartDate: $conflictingStartDate,
            conflictingEndDate: $conflictingEndDate,
        );
    }
}
