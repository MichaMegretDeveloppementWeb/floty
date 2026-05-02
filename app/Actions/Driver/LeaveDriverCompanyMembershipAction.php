<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Data\User\Driver\LeaveDriverCompanyMembershipData;
use App\Enums\Driver\FutureContractsResolutionMode;
use App\Exceptions\Driver\DriverMembershipNotFoundException;
use App\Exceptions\Driver\LeaveResolutionInvalidException;
use App\Models\Contract;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Workflow Q6 (sortie d'un driver d'une entreprise) :
 *
 * 1. Trouver la membership active driver↔company (left_at NULL).
 *    Si introuvable → DriverMembershipNotFoundException.
 * 2. Lister les contrats à venir (start_date > leftAt) du driver dans cette company.
 * 3. Selon `futureContractsResolution` :
 *    - None    : aucun contrat à résoudre, pose simple de left_at.
 *    - Replace : valider d'abord TOUT le replacementMap (driver actif sur la
 *      période exacte de chaque contrat), puis muter ; null = détacher
 *      individuellement ce contrat.
 *    - Detach  : tous les contrats à venir passent à `driver_id = NULL` (1 query batch).
 * 4. Pose `left_at` sur la pivot.
 *
 * Validation et écritures sont scindées en 2 passes pour éviter un rollback
 * partiel : on valide TOUT le replacementMap avant d'ouvrir la transaction.
 */
final class LeaveDriverCompanyMembershipAction
{
    public function __construct(
        private readonly DriverReadRepositoryInterface $driverReadRepo,
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
        private readonly ContractWriteRepositoryInterface $contractWriteRepo,
        private readonly CompanyReadRepositoryInterface $companyReadRepo,
    ) {}

    public function execute(Driver $driver, int $companyId, LeaveDriverCompanyMembershipData $data): void
    {
        $leftAt = Carbon::parse($data->leftAt);

        // 1. Trouver la membership active — throw avant la transaction
        $pivot = $this->driverReadRepo->findActiveMembership($driver->id, $companyId);
        if ($pivot === null) {
            throw DriverMembershipNotFoundException::forActiveMembership($driver->id, $companyId);
        }

        // 2. Lister les contrats à venir
        $futureContracts = $this->driverReadRepo->listFutureContractsInCompany(
            $driver->id,
            $companyId,
            $leftAt,
        );

        // 3. Validation préalable du replacementMap (mode Replace uniquement)
        if (
            $data->futureContractsResolution === FutureContractsResolutionMode::Replace
            && $futureContracts->isNotEmpty()
        ) {
            $this->validateReplacementMap($driver, $companyId, $futureContracts, $data->replacementMap);
        }

        // 4. Mutations en transaction
        DB::transaction(function () use ($pivot, $leftAt, $futureContracts, $data): void {
            if ($futureContracts->isNotEmpty()) {
                match ($data->futureContractsResolution) {
                    FutureContractsResolutionMode::Replace => $this->applyReplace($futureContracts, $data->replacementMap),
                    FutureContractsResolutionMode::Detach => $this->applyDetach($futureContracts),
                    FutureContractsResolutionMode::None => null,
                };
            }

            $this->driverWriteRepo->setLeaveDate((int) $pivot->id, $leftAt);
        });
    }

    /**
     * Première passe : pure validation. Lève si le replacementMap est
     * incohérent (entrée manquante, driver invalide, driver pas actif sur
     * la période, ou driver pointant vers lui-même).
     *
     * @param  Collection<int, Contract>  $contracts
     * @param  array<int, ?int>  $replacementMap
     */
    private function validateReplacementMap(
        Driver $driver,
        int $companyId,
        Collection $contracts,
        array $replacementMap,
    ): void {
        $company = $this->companyReadRepo->findById($companyId);
        if ($company === null) {
            // Cas dégénéré : pivot existait mais la company a disparu — ne devrait
            // jamais arriver vu le restrictOnDelete sur la pivot. Défense en profondeur.
            throw DriverMembershipNotFoundException::forActiveMembership($driver->id, $companyId);
        }

        foreach ($contracts as $contract) {
            if (! array_key_exists($contract->id, $replacementMap)) {
                throw LeaveResolutionInvalidException::missingReplacement($contract->id);
            }

            $replacementId = $replacementMap[$contract->id];
            if ($replacementId === null) {
                continue; // null = détacher ce contrat individuellement, pas de validation requise
            }

            // Interdire le driver sortant comme remplaçant de lui-même
            if ($replacementId === $driver->id) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, $replacementId);
            }

            $replacement = $this->driverReadRepo->findById($replacementId);
            if ($replacement === null) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, $replacementId);
            }

            $isActive = $replacement->isActiveInCompanyDuring(
                $company,
                Carbon::parse($contract->start_date),
                Carbon::parse($contract->end_date),
            );

            if (! $isActive) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, $replacementId);
            }
        }
    }

    /**
     * Deuxième passe : pure mutation, validation déjà faite en amont.
     *
     * @param  Collection<int, Contract>  $contracts
     * @param  array<int, ?int>  $replacementMap
     */
    private function applyReplace(Collection $contracts, array $replacementMap): void
    {
        foreach ($contracts as $contract) {
            $this->contractWriteRepo->reassignDriver($contract->id, $replacementMap[$contract->id]);
        }
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     */
    private function applyDetach(Collection $contracts): void
    {
        $ids = $contracts->pluck('id')->all();
        $this->contractWriteRepo->bulkReassignDriver($ids, null);
    }
}
