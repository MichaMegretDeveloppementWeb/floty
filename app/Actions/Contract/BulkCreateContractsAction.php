<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\BulkStoreContractsData;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Création atomique de N contrats partageant une plage commune,
 * un type, et la même entreprise affectataire — typiquement
 * l'attribution rapide multi-véhicules depuis le planning
 * (chantier 04.G).
 *
 * Cf. ADR-0013 R3 (orchestration multi-écritures coordonnées).
 *
 * **Comportement transactionnel** : la transaction démarre avant
 * la première vérification d'overlap. Si l'un des véhicules
 * présente un conflit, l'exception bloque immédiatement et la
 * transaction est rollback (aucun contrat créé). C'est le
 * comportement attendu : l'utilisateur soumet un lot global ou
 * rien.
 *
 * @return list<int> IDs des contrats créés (ordre identique à
 *                   `vehicleIds` du payload).
 */
final readonly class BulkCreateContractsAction
{
    public function __construct(
        private ContractReadRepositoryInterface $reader,
        private ContractWriteRepositoryInterface $writer,
    ) {}

    /**
     * @return list<int>
     */
    public function execute(BulkStoreContractsData $data): array
    {
        // La plage est commune à tous les vehicleIds par construction
        // du DTO, donc 1 seul calcul de type pour le batch entier.
        $contractType = Contract::deriveTypeFromDates($data->startDate, $data->endDate);

        return DB::transaction(function () use ($data, $contractType): array {
            $rows = [];

            foreach ($data->vehicleIds as $vehicleId) {
                // Pré-vérification applicative : un overlap suffit à
                // bloquer le lot entier (rollback automatique de la
                // transaction).
                $conflict = $this->reader->findOverlapping(
                    vehicleId: $vehicleId,
                    startDate: $data->startDate,
                    endDate: $data->endDate,
                );

                if ($conflict !== null) {
                    throw ContractOverlapException::fromConflict(
                        vehicleId: $vehicleId,
                        startDate: $data->startDate,
                        endDate: $data->endDate,
                        conflictingContractId: $conflict->id,
                        conflictingStartDate: $conflict->start_date->toDateString(),
                        conflictingEndDate: $conflict->end_date->toDateString(),
                    );
                }

                $rows[] = [
                    'vehicle_id' => $vehicleId,
                    'company_id' => $data->companyId,
                    'driver_id' => $data->driverId,
                    'start_date' => $data->startDate,
                    'end_date' => $data->endDate,
                    'contract_reference' => $data->contractReference,
                    'contract_type' => $contractType->value,
                    'notes' => $data->notes,
                ];
            }

            return $this->writer->insertManyRows($rows);
        });
    }
}
