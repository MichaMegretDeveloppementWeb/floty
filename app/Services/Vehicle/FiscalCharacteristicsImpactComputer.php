<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Enums\Vehicle\FiscalCharacteristicsImpactType;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;

/**
 * Pure-function computer of side effects induced by editing one VFC on
 * its neighbours in the vehicle history.
 *
 * No IO, no state. The Action passes the rest of the history (without
 * the edited VFC) and the proposed new bounds; the returned list of
 * `Delete` / `AdjustEffectiveFrom` / `AdjustEffectiveTo` impacts is
 * applied after the VFC `UPDATE`.
 *
 * Algorithm ·
 *   1. Classify each other VFC against `[newFrom, newTo]` ·
 *      - fully contained → `Delete`
 *      - left overlap (starts before newFrom, ends within) →
 *        `AdjustEffectiveTo` to `newFrom − 1`
 *      - right overlap (starts within, ends after newTo) →
 *        `AdjustEffectiveFrom` to `newTo + 1`
 *      - entirely before or after → candidate predecessor / successor
 *   2. Fill the immediate adjacency gap with the retained predecessor
 *      and successor unless an overlap adjustment already took that slot.
 *
 * Open right bounds (`effective_to === null`, current version) are
 * normalised to `+∞` for comparisons.
 */
final readonly class FiscalCharacteristicsImpactComputer
{
    /**
     * @param  iterable<VehicleFiscalCharacteristics>  $others  All VFCs of the vehicle except the edited one
     * @return list<FiscalCharacteristicsImpact>
     */
    public function compute(
        iterable $others,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): array {
        /** @var list<FiscalCharacteristicsImpact> $impacts */
        $impacts = [];

        $candidatePredecessor = null;
        $candidateSuccessor = null;
        $candidatePredecessorFrom = null;
        $candidateSuccessorFrom = null;

        foreach ($others as $v) {
            $vFrom = $v->effective_from->toImmutable();
            $vTo = $v->effective_to === null
                ? null
                : $v->effective_to->toImmutable();

            if ($this->isEngulfedBy($vFrom, $vTo, $newFrom, $newTo)) {
                $impacts[] = FiscalCharacteristicsImpact::delete($v);

                continue;
            }

            // Left overlap · v starts before newFrom and its
            // effective_to falls within [newFrom, newTo]. Special case
            // included · v is the current open VFC and the new range
            // also opens (vTo=null AND newTo=null); the previous
            // current must be shortened to the day before newFrom so
            // the DB trigger accepts the INSERT.
            if (
                $vFrom->lessThan($newFrom)
                && (
                    ($vTo !== null
                        && $vTo->greaterThanOrEqualTo($newFrom)
                        && ($newTo === null || $vTo->lessThanOrEqualTo($newTo)))
                    || ($vTo === null && $newTo === null)
                )
            ) {
                $impacts[] = FiscalCharacteristicsImpact::adjustEffectiveTo(
                    $v,
                    $newFrom->subDay(),
                );

                continue;
            }

            // Right overlap · v starts within [newFrom, newTo] and
            // ends after newTo (or is open-ended).
            if (
                $newTo !== null
                && $vFrom->greaterThanOrEqualTo($newFrom)
                && $vFrom->lessThanOrEqualTo($newTo)
                && ($vTo === null || $vTo->greaterThan($newTo))
            ) {
                $impacts[] = FiscalCharacteristicsImpact::adjustEffectiveFrom(
                    $v,
                    $newTo->addDay(),
                );

                continue;
            }

            // No overlap → entirely before or entirely after.
            if ($vTo !== null && $vTo->lessThan($newFrom)) {
                if (
                    $candidatePredecessorFrom === null
                    || $vFrom->greaterThan($candidatePredecessorFrom)
                ) {
                    $candidatePredecessor = $v;
                    $candidatePredecessorFrom = $vFrom;
                }

                continue;
            }

            if ($newTo !== null && $vFrom->greaterThan($newTo)) {
                if (
                    $candidateSuccessorFrom === null
                    || $vFrom->lessThan($candidateSuccessorFrom)
                ) {
                    $candidateSuccessor = $v;
                    $candidateSuccessorFrom = $vFrom;
                }

                continue;
            }

            // Pathological · v strictly contains [newFrom, newTo].
            // Production invariants forbid this, but we ignore it
            // defensively. Action-side `guardNoOverlapResidual` catches
            // any leftover.
        }

        // Fill the predecessor gap unless another VFC was already
        // shortened to newFrom-1 by left-overlap handling (otherwise
        // two adjusts target the same border and the DB trigger
        // rejects the overlap).
        if ($candidatePredecessor !== null) {
            $expectedTo = $newFrom->subDay();
            $currentTo = $candidatePredecessor->effective_to !== null
                ? $candidatePredecessor->effective_to->toImmutable()
                : null;

            $slotAlreadyFilled = $this->anyAdjustEffectiveToMatches($impacts, $expectedTo);

            if (! $slotAlreadyFilled && ($currentTo === null || ! $currentTo->equalTo($expectedTo))) {
                $impacts[] = FiscalCharacteristicsImpact::adjustEffectiveTo(
                    $candidatePredecessor,
                    $expectedTo,
                );
            }
        }

        // Fill the successor gap. Skipped when the new range has no
        // right bound (no successor possible). Same anti-collision
        // guard as above.
        if ($candidateSuccessor !== null && $newTo !== null) {
            $expectedFrom = $newTo->addDay();
            $currentFrom = $candidateSuccessor->effective_from->toImmutable();

            $slotAlreadyFilled = $this->anyAdjustEffectiveFromMatches($impacts, $expectedFrom);

            if (! $slotAlreadyFilled && ! $currentFrom->equalTo($expectedFrom)) {
                $impacts[] = FiscalCharacteristicsImpact::adjustEffectiveFrom(
                    $candidateSuccessor,
                    $expectedFrom,
                );
            }
        }

        return $impacts;
    }

    /**
     * @param  list<FiscalCharacteristicsImpact>  $impacts
     */
    private function anyAdjustEffectiveToMatches(array $impacts, CarbonImmutable $target): bool
    {
        foreach ($impacts as $impact) {
            if (
                $impact->type === FiscalCharacteristicsImpactType::AdjustEffectiveTo
                && $impact->newEffectiveTo !== null
                && $impact->newEffectiveTo->equalTo($target)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<FiscalCharacteristicsImpact>  $impacts
     */
    private function anyAdjustEffectiveFromMatches(array $impacts, CarbonImmutable $target): bool
    {
        foreach ($impacts as $impact) {
            if (
                $impact->type === FiscalCharacteristicsImpactType::AdjustEffectiveFrom
                && $impact->newEffectiveFrom !== null
                && $impact->newEffectiveFrom->equalTo($target)
            ) {
                return true;
            }
        }

        return false;
    }

    private function isEngulfedBy(
        CarbonImmutable $vFrom,
        ?CarbonImmutable $vTo,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): bool {
        if ($vFrom->lessThan($newFrom)) {
            return false;
        }

        // newTo == null → newRange = [newFrom, +∞). Any vTo (including
        // null) is ≤ +∞, so v is engulfed as soon as vFrom ≥ newFrom.
        if ($newTo === null) {
            return true;
        }

        return $vTo !== null && $vTo->lessThanOrEqualTo($newTo);
    }
}
