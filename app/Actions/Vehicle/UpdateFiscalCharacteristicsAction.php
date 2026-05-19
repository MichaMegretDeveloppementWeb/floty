<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Enums\Vehicle\FiscalCharacteristicsImpactType;
use App\Exceptions\Vehicle\FiscalCharacteristicsRequiresConfirmationException;
use App\Exceptions\Vehicle\InvalidFiscalCharacteristicsBoundsException;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\FiscalCharacteristicsImpactComputer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Updates a single VFC from the Historique modal, with automatic
 * cascade adjustments on its neighbours.
 *
 * Pipeline (transaction):
 *   1. Bounds validation: `effective_from` <= `effective_to`; forbid
 *      switching between current and bounded (preserves the invariant
 *      "0 or 1 current version per vehicle").
 *   2. Impacts on other VFCs computed via
 *      {@see FiscalCharacteristicsImpactComputer} (Delete /
 *      AdjustEffectiveTo / AdjustEffectiveFrom).
 *   3. User confirmation: a destructive impact (Delete) with
 *      `data.confirmed === false` raises
 *      {@see FiscalCharacteristicsRequiresConfirmationException}.
 *   4. Apply: UPDATE the edited VFC, then run each impact in order
 *      (DELETE / SET effective_to / SET effective_from).
 *   5. Return the refreshed VFC. The applied impacts are exposed via
 *      {@see self::$lastImpacts} so the controller can push an info
 *      toast.
 *
 * Maintains the "contiguous ranges, no overlap" invariant at the
 * vehicle scope; the algorithm tolerates more than one neighbour
 * touched (large displacements).
 */
final class UpdateFiscalCharacteristicsAction
{
    /** @var list<FiscalCharacteristicsImpact> */
    private array $lastImpacts = [];

    public function __construct(
        private readonly VehicleFiscalCharacteristicsReadRepositoryInterface $reader,
        private readonly VehicleFiscalCharacteristicsWriteRepositoryInterface $writer,
        private readonly FiscalCharacteristicsImpactComputer $impactComputer,
    ) {}

    public function execute(
        int $fiscalId,
        UpdateFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics {
        return DB::transaction(function () use ($fiscalId, $data): VehicleFiscalCharacteristics {
            $current = $this->reader->findById($fiscalId);

            $newFrom = CarbonImmutable::parse($data->effectiveFrom);
            $newTo = $data->effectiveTo === null
                ? null
                : CarbonImmutable::parse($data->effectiveTo);

            $this->guardBoundsConsistency($current, $newFrom, $newTo);

            $others = $this->reader->findOthersForVehicle($current->vehicle_id, $current->id);

            $this->guardNotStrictlyInsideExisting($others, $newFrom, $newTo);

            $impacts = $this->impactComputer->compute($others, $newFrom, $newTo);

            $hasDestructive = $this->hasDestructiveImpact($impacts);
            if ($hasDestructive && ! $data->confirmed) {
                throw FiscalCharacteristicsRequiresConfirmationException::withImpacts($impacts);
            }

            // Strict ordering against the DB trigger "no overlapping
            // effective period": free the slot first (DELETE + neighbour
            // shrinks), then move the edited VFC, then close the
            // remaining gaps.
            $this->applyImpacts(array_values(array_filter(
                $impacts,
                static fn (FiscalCharacteristicsImpact $i): bool => $i->mustApplyBeforeUpdate(),
            )));

            $updated = $this->writer->updateBoundsAndFields($fiscalId, $data);

            $this->applyImpacts(array_values(array_filter(
                $impacts,
                static fn (FiscalCharacteristicsImpact $i): bool => ! $i->mustApplyBeforeUpdate(),
            )));

            $this->lastImpacts = $impacts;

            return $updated;
        });
    }

    /**
     * Impacts applied during the last `execute()`. Used by the
     * controller to compose the info toast.
     *
     * @return list<FiscalCharacteristicsImpact>
     */
    public function lastImpacts(): array
    {
        return $this->lastImpacts;
    }

    /**
     * Checks the consistency of the proposed bounds in isolation
     * (no history lookup).
     */
    private function guardBoundsConsistency(
        VehicleFiscalCharacteristics $current,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        if ($newTo !== null && $newFrom->greaterThan($newTo)) {
            throw InvalidFiscalCharacteristicsBoundsException::endBeforeStart();
        }

        $wasCurrent = $current->effective_to === null;
        $becomesCurrent = $newTo === null;

        // A current VFC cannot be transformed into a bounded one this
        // way; the user must use "New version" instead to close the
        // current row with a proper succession.
        if ($wasCurrent && ! $becomesCurrent) {
            throw InvalidFiscalCharacteristicsBoundsException::cannotTransformCurrentToBounded();
        }

        // Reverse invariant: a non-current VFC becoming current is
        // only allowed if none other is already current.
        if (! $wasCurrent && $becomesCurrent) {
            $other = $this->reader->findCurrentForVehicle($current->vehicle);

            if ($other !== null && $other->id !== $current->id) {
                throw InvalidFiscalCharacteristicsBoundsException::cannotTransformHistoricToCurrent();
            }
        }
    }

    /**
     * Refuses an edit whose new range [newFrom, newTo] is strictly
     * contained inside another existing range. Without this guard the
     * DB trigger rejects the UPDATE with an opaque message.
     *
     * @param  iterable<VehicleFiscalCharacteristics>  $others
     */
    private function guardNotStrictlyInsideExisting(
        iterable $others,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        // An open-ended new range cannot be strictly contained; see
        // CreateFiscalCharacteristicsAction for the rationale.
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
