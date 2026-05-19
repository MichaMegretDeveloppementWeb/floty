<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreFiscalCharacteristicsData;
use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Enums\Vehicle\FiscalCharacteristicsImpactType;
use App\Exceptions\Vehicle\FiscalCharacteristicsRequiresConfirmationException;
use App\Exceptions\Vehicle\InvalidFiscalCharacteristicsBoundsException;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\FiscalCharacteristicsImpactComputer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Inserts a new VFC in the history of a vehicle from the Historique
 * modal, with automatic cascade adjustments on neighbouring rows
 * (counterpart of {@see UpdateFiscalCharacteristicsAction}).
 *
 * Pipeline (transaction):
 *   1. Bounds validation: `effective_from` <= `effective_to`, no exact
 *      collision with an existing `effective_from`, no strictly inside
 *      an existing range.
 *   2. Impacts on neighbours computed via
 *      {@see FiscalCharacteristicsImpactComputer} (Delete /
 *      AdjustEffectiveTo / AdjustEffectiveFrom).
 *   3. User confirmation: a destructive impact (Delete) with
 *      `data.confirmed === false` raises
 *      {@see FiscalCharacteristicsRequiresConfirmationException}.
 *   4. Apply: DELETE/Adjust before INSERT to free the slot, then
 *      INSERT, then AdjustFrom after INSERT to close the following
 *      gap.
 *   5. Return the inserted VFC. The applied impacts are exposed via
 *      {@see self::$lastImpacts} so the controller can push an info
 *      toast.
 *
 * Preserves the invariant "0 or 1 current version per vehicle".
 */
final class CreateFiscalCharacteristicsAction
{
    /** @var list<FiscalCharacteristicsImpact> */
    private array $lastImpacts = [];

    public function __construct(
        private readonly VehicleFiscalCharacteristicsReadRepositoryInterface $reader,
        private readonly VehicleFiscalCharacteristicsWriteRepositoryInterface $writer,
        private readonly FiscalCharacteristicsImpactComputer $impactComputer,
    ) {}

    public function execute(
        Vehicle $vehicle,
        StoreFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics {
        return DB::transaction(function () use ($vehicle, $data): VehicleFiscalCharacteristics {
            $newFrom = CarbonImmutable::parse($data->effectiveFrom);
            $newTo = $data->effectiveTo === null
                ? null
                : CarbonImmutable::parse($data->effectiveTo);

            $this->guardBoundsConsistency($newFrom, $newTo);

            // All VFCs of the vehicle (none to exclude since we create).
            $others = $this->reader->findOthersForVehicle($vehicle->id, 0);

            $this->guardEffectiveFromUnique($others, $newFrom);
            $this->guardNotStrictlyInsideExisting($others, $newFrom, $newTo);

            $impacts = $this->impactComputer->compute($others, $newFrom, $newTo);

            $hasDestructive = $this->hasDestructiveImpact($impacts);
            if ($hasDestructive && ! $data->confirmed) {
                throw FiscalCharacteristicsRequiresConfirmationException::withImpacts($impacts);
            }

            // Strict ordering against the DB trigger "no overlapping
            // effective period": DELETE + neighbour shrinks free the
            // slot BEFORE the INSERT, then INSERT, then post-insert
            // AdjustFrom closes the next gap.
            $this->applyImpacts(array_values(array_filter(
                $impacts,
                static fn (FiscalCharacteristicsImpact $i): bool => $i->mustApplyBeforeUpdate(),
            )));

            $created = $this->writer->createFromBoundsAndFields($vehicle->id, $data);

            $this->applyImpacts(array_values(array_filter(
                $impacts,
                static fn (FiscalCharacteristicsImpact $i): bool => ! $i->mustApplyBeforeUpdate(),
            )));

            $this->lastImpacts = $impacts;

            return $created;
        });
    }

    /**
     * @return list<FiscalCharacteristicsImpact>
     */
    public function lastImpacts(): array
    {
        return $this->lastImpacts;
    }

    private function guardBoundsConsistency(
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        if ($newTo !== null && $newFrom->greaterThan($newTo)) {
            throw InvalidFiscalCharacteristicsBoundsException::endBeforeStart();
        }
    }

    /**
     * Refuses a create whose `effective_from` exactly matches another
     * VFC. Ambiguity between "add" and "edit": the user must change
     * the date or use the edit modal of the existing VFC.
     *
     * @param  iterable<VehicleFiscalCharacteristics>  $others
     */
    private function guardEffectiveFromUnique(
        iterable $others,
        CarbonImmutable $newFrom,
    ): void {
        foreach ($others as $v) {
            $vFrom = $v->effective_from->toImmutable();
            if ($vFrom->equalTo($newFrom)) {
                throw InvalidFiscalCharacteristicsBoundsException::effectiveFromCollidesExisting(
                    $newFrom->toDateString(),
                );
            }
        }
    }

    /**
     * Refuses a create whose range [newFrom, newTo] is strictly contained
     * inside an existing VFC range. Without this guard the DB trigger
     * rejects the INSERT with an opaque technical message; UX-wise the
     * user must first edit the surrounding VFC.
     *
     * @param  iterable<VehicleFiscalCharacteristics>  $others
     */
    private function guardNotStrictlyInsideExisting(
        iterable $others,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        // An open-ended new range (newTo === null) extends to +∞ and
        // cannot be strictly contained in any existing range; the left
        // overlap is handled by the ImpactComputer (shrinks the
        // neighbour to newFrom-1).
        if ($newTo === null) {
            return;
        }

        foreach ($others as $v) {
            $vFrom = $v->effective_from->toImmutable();
            $vTo = $v->effective_to === null
                ? null
                : $v->effective_to->toImmutable();

            if (! $vFrom->lessThan($newFrom)) {
                continue;
            }

            $endsAfterNewRange = $vTo === null || $vTo->greaterThan($newTo);

            if ($endsAfterNewRange) {
                throw InvalidFiscalCharacteristicsBoundsException::newRangeStrictlyInsideExisting(
                    $vFrom->toDateString(),
                    $vTo?->toDateString(),
                    $newFrom->toDateString(),
                );
            }
        }
    }

    /**
     * @param  list<FiscalCharacteristicsImpact>  $impacts
     */
    private function hasDestructiveImpact(array $impacts): bool
    {
        foreach ($impacts as $impact) {
            if ($impact->isDestructive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<FiscalCharacteristicsImpact>  $impacts
     */
    private function applyImpacts(array $impacts): void
    {
        foreach ($impacts as $impact) {
            match ($impact->type) {
                FiscalCharacteristicsImpactType::Delete => $this->writer->deleteOne($impact->targetId),
                FiscalCharacteristicsImpactType::AdjustEffectiveTo => $this->writer->setEffectiveTo(
                    $impact->targetId,
                    $impact->newEffectiveTo,
                ),
                FiscalCharacteristicsImpactType::AdjustEffectiveFrom => $this->writer->setEffectiveFrom(
                    $impact->targetId,
                    $impact->newEffectiveFrom,
                ),
            };
        }
    }
}
