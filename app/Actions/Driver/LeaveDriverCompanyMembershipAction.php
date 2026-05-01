<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Data\User\Driver\LeaveDriverCompanyMembershipData;
use App\Exceptions\Driver\LeaveResolutionInvalidException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Workflow Q6 (sortie d'un driver d'une entreprise) :
 *
 * 1. Trouver la membership active driver↔company (left_at NULL)
 * 2. Lister les contrats à venir (start_date > leftAt) du driver dans cette company
 * 3. Selon `futureContractsResolution` :
 *    - 'none' : aucun contrat à résoudre, pose simple de left_at
 *    - 'replace' : pour chaque contrat, vérifier que le replacementDriverId
 *      est actif dans la company sur la période exacte du contrat, puis
 *      mettre à jour `contracts.driver_id`
 *    - 'detach' : tous les contrats à venir passent à `driver_id = NULL`
 * 4. Pose `left_at` sur la pivot
 *
 * Le tout en transaction.
 */
final class LeaveDriverCompanyMembershipAction
{
    public function __construct(
        private readonly DriverReadRepositoryInterface $driverReadRepo,
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
    ) {}

    public function execute(Driver $driver, int $companyId, LeaveDriverCompanyMembershipData $data): void
    {
        $leftAt = Carbon::parse($data->leftAt);

        DB::transaction(function () use ($driver, $companyId, $leftAt, $data): void {
            // 1. Trouver la membership active
            $pivot = DB::table('driver_company')
                ->where('driver_id', $driver->id)
                ->where('company_id', $companyId)
                ->whereNull('left_at')
                ->orderByDesc('joined_at')
                ->first();

            if ($pivot === null) {
                return;
            }

            // 2. Résoudre les contrats à venir selon le mode choisi
            $futureContracts = $this->driverReadRepo->listFutureContractsInCompany(
                $driver->id,
                $companyId,
                $leftAt,
            );

            if ($futureContracts->isNotEmpty()) {
                match ($data->futureContractsResolution) {
                    'replace' => $this->resolveByReplace($driver, $companyId, $futureContracts, $data->replacementMap),
                    'detach' => $this->resolveByDetach($futureContracts),
                    'none' => null, // l'utilisateur a indiqué qu'il n'y a rien à résoudre — on ignore
                    default => null,
                };
            }

            // 3. Pose left_at
            $this->driverWriteRepo->setLeaveDate((int) $pivot->id, $leftAt);
        });
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @param  array<int, ?int>  $replacementMap
     */
    private function resolveByReplace(
        Driver $driver,
        int $companyId,
        $contracts,
        array $replacementMap,
    ): void {
        foreach ($contracts as $contract) {
            if (! array_key_exists($contract->id, $replacementMap)) {
                throw LeaveResolutionInvalidException::missingReplacement($contract->id);
            }

            $replacementId = $replacementMap[$contract->id];
            if ($replacementId === null) {
                // null = on détache ce contrat individuellement
                Contract::query()->where('id', $contract->id)->update(['driver_id' => null]);

                continue;
            }

            // Vérifier que le replacement est actif dans la company sur la période du contrat
            $replacement = $this->driverReadRepo->findById((int) $replacementId);
            if ($replacement === null) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, (int) $replacementId);
            }

            $companyModel = Company::query()->find($companyId);
            if ($companyModel === null) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, (int) $replacementId);
            }

            $isActive = $replacement->isActiveInCompanyDuring(
                $companyModel,
                Carbon::parse($contract->start_date),
                Carbon::parse($contract->end_date),
            );

            if (! $isActive) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, (int) $replacementId);
            }

            Contract::query()->where('id', $contract->id)->update(['driver_id' => $replacementId]);
        }
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     */
    private function resolveByDetach($contracts): void
    {
        $ids = $contracts->pluck('id')->all();
        Contract::query()->whereIn('id', $ids)->update(['driver_id' => null]);
    }
}
