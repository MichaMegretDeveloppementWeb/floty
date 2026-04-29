<?php

declare(strict_types=1);

namespace App\Exceptions\Contract;

use App\Exceptions\BaseAppException;
use Illuminate\Support\Carbon;

/**
 * Un contrat est créé ou modifié sur une plage qui chevauche au moins
 * un autre contrat actif du même véhicule.
 *
 * Cf. ADR-0014 D5 : un véhicule ne peut avoir deux contrats actifs
 * (non soft-deleted) qui se chevauchent dans le temps. Le trigger
 * MySQL `contracts_no_overlap_*` est la source de vérité de cet
 * invariant ; cette exception est la défense en profondeur côté Action
 * pour produire un message FR explicite avant que la requête atteigne
 * la DB.
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
                'La plage du contrat (%s → %s) chevauche un contrat existant '
                .'sur ce véhicule (du %s au %s). Ajustez les bornes ou supprimez '
                .'le contrat en conflit avant d\'enregistrer.',
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
