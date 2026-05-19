<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Enums\Vehicle\FiscalCharacteristicsExtensionStrategy;
use App\Exceptions\Vehicle\CannotDeleteOnlyVersionException;
use App\Exceptions\Vehicle\InvalidFiscalCharacteristicsBoundsException;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a VFC from the Historique modal with automatic gap filling.
 *
 * Pipeline (transaction):
 *   1. Refuse to delete the only remaining version
 *      ({@see CannotDeleteOnlyVersionException}).
 *   2. Apply the user-chosen extension strategy:
 *      - `ExtendPrevious`: previous VFC absorbs the gap (its
 *        `effective_to` is pushed up to the deleted row's
 *        `effective_to`, or to null if the deleted row was current).
 *      - `ExtendNext`: next VFC absorbs the gap (its `effective_from`
 *        is moved back to the deleted row's `effective_from`).
 *   3. Refuse the strategy when no compatible neighbour exists.
 *   4. DELETE the target.
 */
final readonly class DeleteFiscalCharacteristicsAction
{
    public function __construct(
        private VehicleFiscalCharacteristicsReadRepositoryInterface $reader,
        private VehicleFiscalCharacteristicsWriteRepositoryInterface $writer,
    ) {}

    public function execute(
        int $fiscalId,
        FiscalCharacteristicsExtensionStrategy $strategy,
    ): void {
        DB::transaction(function () use ($fiscalId, $strategy): void {
            $vfc = $this->reader->findById($fiscalId);
            $count = $this->reader->countForVehicle($vfc->vehicle_id);

            if ($count <= 1) {
                throw CannotDeleteOnlyVersionException::make();
            }

            // Capture the neighbour BEFORE delete; findAdjacent can no
            // longer resolve it once the pivot is gone.
            $neighbor = $strategy === FiscalCharacteristicsExtensionStrategy::ExtendPrevious
                ? $this->reader->findAdjacent($vfc, -1)
                : $this->reader->findAdjacent($vfc, 1);

            if ($neighbor === null) {
                throw $strategy === FiscalCharacteristicsExtensionStrategy::ExtendPrevious
                    ? InvalidFiscalCharacteristicsBoundsException::noPreviousVersionToExtend()
                    : InvalidFiscalCharacteristicsBoundsException::noNextVersionToExtend();
            }

            // DELETE first then extend the neighbour, otherwise the
            // MySQL uniqueness trigger detects a transient overlap
            // while the target still exists.
            $this->writer->deleteOne($fiscalId);

            if ($strategy === FiscalCharacteristicsExtensionStrategy::ExtendPrevious) {
                $this->writer->setEffectiveTo($neighbor->id, $vfc->effective_to);
            } else {
                $this->writer->setEffectiveFrom($neighbor->id, $vfc->effective_from);
            }
        });
    }
}
