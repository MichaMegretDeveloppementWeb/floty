<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\StoreContractData;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Création d'un contrat avec validation applicative anti-overlap
 * (défense en profondeur avant le trigger DB, cf. ADR-0014 D5).
 *
 * Le trigger MySQL `contracts_no_overlap_*` reste la **source de vérité**
 * de l'invariant — il garantit la cohérence même en cas de race
 * inter-requêtes que la transaction Laravel ne couvre pas (READ
 * COMMITTED par défaut). La pré-vérification applicative ici sert à
 * produire un message FR explicite quand le check passe en amont, et la
 * transaction garantit qu'aucun side-effect (events Eloquent, etc.) ne
 * laisse un état partiel si l'écriture plante après la pré-vérification.
 */
final readonly class StoreContractAction
{
    public function __construct(
        private ContractReadRepositoryInterface $reader,
        private ContractWriteRepositoryInterface $writer,
    ) {}

    public function execute(StoreContractData $data): Contract
    {
        return DB::transaction(function () use ($data): Contract {
            $conflict = $this->reader->findOverlapping(
                vehicleId: $data->vehicleId,
                startDate: $data->startDate,
                endDate: $data->endDate,
            );

            if ($conflict !== null) {
                throw ContractOverlapException::fromConflict(
                    vehicleId: $data->vehicleId,
                    startDate: $data->startDate,
                    endDate: $data->endDate,
                    conflictingContractId: $conflict->id,
                    conflictingStartDate: $conflict->start_date->toDateString(),
                    conflictingEndDate: $conflict->end_date->toDateString(),
                );
            }

            $contractType = Contract::deriveTypeFromDates($data->startDate, $data->endDate);

            return $this->writer->create($data, $contractType);
        });
    }
}
