<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\UpdateContractData;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Contract;

/**
 * Mise à jour d'un contrat avec re-validation applicative anti-overlap
 * (défense en profondeur, cf. ADR-0014 D5). La ligne courante est
 * exclue de la recherche de conflit via `excludeId`.
 */
final readonly class UpdateContractAction
{
    public function __construct(
        private ContractReadRepositoryInterface $reader,
        private ContractWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $contractId, UpdateContractData $data): Contract
    {
        $conflict = $this->reader->findOverlapping(
            vehicleId: $data->vehicleId,
            startDate: $data->startDate,
            endDate: $data->endDate,
            excludeId: $contractId,
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

        return $this->writer->update($contractId, $data);
    }
}
