<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\StoreContractData;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Contract;

/**
 * Création d'un contrat avec validation applicative anti-overlap
 * (défense en profondeur avant le trigger DB, cf. ADR-0014 D5).
 *
 * Le trigger MySQL est la source de vérité de l'invariant ; ici on
 * vérifie en amont pour produire un message FR explicite si l'UI a
 * laissé passer un cas (ex. autre user a créé un contrat entre-temps).
 */
final readonly class StoreContractAction
{
    public function __construct(
        private ContractReadRepositoryInterface $reader,
        private ContractWriteRepositoryInterface $writer,
    ) {}

    public function execute(StoreContractData $data): Contract
    {
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

        return $this->writer->create($data);
    }
}
