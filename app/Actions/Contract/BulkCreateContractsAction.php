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
 * un type, et la même entreprise affectataire - typiquement
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
            // Lot 3 D01 · 1 query batch au lieu de N appels findOverlapping ·
            // charge tous les contrats existants des véhicules listés qui
            // chevauchent la plage commune `[startDate, endDate]`. La
            // boucle ci-dessous fait du fail-fast en mémoire pour
            // préserver la sémantique historique (1er overlap rencontré
            // → exception, rollback transaction, lot entier rejeté).
            $existingOverlaps = $this->reader->findAllOverlappingForVehicles(
                vehicleIds: $data->vehicleIds,
                startDate: $data->startDate,
                endDate: $data->endDate,
            );

            // Index par vehicle_id pour lookup O(1) dans la boucle ·
            // chaque entrée contient le **premier** contrat conflictuel
            // (les contrats sont triés par `vehicle_id, start_date` côté
            // SQL, donc la 1ère occurrence par véhicule est déterministe).
            $overlapByVehicleId = [];
            foreach ($existingOverlaps as $contract) {
                $overlapByVehicleId[$contract->vehicle_id] ??= $contract;
            }

            $rows = [];
            foreach ($data->vehicleIds as $vehicleId) {
                $conflict = $overlapByVehicleId[$vehicleId] ?? null;

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
                    'start_date' => $data->startDate,
                    'end_date' => $data->endDate,
                    'contract_reference' => $data->contractReference,
                    'contract_type' => $contractType->value,
                    'notes' => $data->notes,
                ];
            }

            $ids = $this->writer->insertManyRows($rows);

            // Attache la même liste de conducteurs à chaque contrat créé
            // (cf. chantier #3 multi-conducteurs). `syncDrivers` est appelé
            // pour chaque contrat - inserts simples sur le pivot, pas de
            // diff puisque nouvelle création. Optimisation pivot batch
            // séparable (Lot 3 dette résiduelle, hors-D01).
            foreach ($ids as $contractId) {
                $this->writer->syncDrivers($contractId, $data->driverIds);
            }

            return $ids;
        });
    }
}
