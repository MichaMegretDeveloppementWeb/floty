<?php

declare(strict_types=1);

namespace App\DTO\Vehicle;

use App\Actions\Vehicle\UpdateFiscalCharacteristicsAction;
use App\Enums\Vehicle\FiscalCharacteristicsImpactType;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\FiscalCharacteristicsImpactComputer;
use Carbon\CarbonImmutable;

/**
 * Side effect that editing a vehicle's fiscal characteristics produces on
 * one of its neighbours in the historisation timeline.
 *
 * Computed by
 * {@see FiscalCharacteristicsImpactComputer} and
 * applied (DELETE / UPDATE of bound) by
 * {@see UpdateFiscalCharacteristicsAction}.
 *
 * Bounds are kept as `Y-m-d` strings to ease comparisons and serialisation
 * in user-facing messages (toast info, modal confirmation). Pure data
 * holder · no business logic.
 */
final readonly class FiscalCharacteristicsImpact
{
    /**
     * @param  string  $targetEffectiveFrom  Existing lower bound (Y-m-d).
     * @param  ?string  $targetEffectiveTo  Existing upper bound (Y-m-d) or null when open-ended.
     */
    public function __construct(
        public FiscalCharacteristicsImpactType $type,
        public int $targetId,
        public string $targetEffectiveFrom,
        public ?string $targetEffectiveTo,
        public ?CarbonImmutable $newEffectiveFrom = null,
        public ?CarbonImmutable $newEffectiveTo = null,
    ) {}

    /**
     * Build a delete impact for the given target.
     */
    public static function delete(VehicleFiscalCharacteristics $target): self
    {
        return new self(
            type: FiscalCharacteristicsImpactType::Delete,
            targetId: $target->id,
            targetEffectiveFrom: $target->effective_from->toDateString(),
            targetEffectiveTo: $target->effective_to?->toDateString(),
        );
    }

    /**
     * Build an impact that shifts the target's upper bound to `$newEffectiveTo`.
     */
    public static function adjustEffectiveTo(
        VehicleFiscalCharacteristics $target,
        CarbonImmutable $newEffectiveTo,
    ): self {
        return new self(
            type: FiscalCharacteristicsImpactType::AdjustEffectiveTo,
            targetId: $target->id,
            targetEffectiveFrom: $target->effective_from->toDateString(),
            targetEffectiveTo: $target->effective_to?->toDateString(),
            newEffectiveTo: $newEffectiveTo,
        );
    }

    /**
     * Build an impact that shifts the target's lower bound to `$newEffectiveFrom`.
     */
    public static function adjustEffectiveFrom(
        VehicleFiscalCharacteristics $target,
        CarbonImmutable $newEffectiveFrom,
    ): self {
        return new self(
            type: FiscalCharacteristicsImpactType::AdjustEffectiveFrom,
            targetId: $target->id,
            targetEffectiveFrom: $target->effective_from->toDateString(),
            targetEffectiveTo: $target->effective_to?->toDateString(),
            newEffectiveFrom: $newEffectiveFrom,
        );
    }

    /**
     * Return true when the impact removes a neighbour entirely.
     */
    public function isDestructive(): bool
    {
        return $this->type === FiscalCharacteristicsImpactType::Delete;
    }

    /**
     * Return true when the impact must be applied before the UPDATE of
     * the edited fiscal-characteristics row to avoid violating the DB
     * trigger that forbids two overlapping periods on the same vehicle.
     *
     * Cascades that shrink or remove a neighbour run BEFORE the UPDATE
     * (they free space); cascades that extend a neighbour run AFTER
     * (extending earlier would temporarily overlap existing bounds).
     */
    public function mustApplyBeforeUpdate(): bool
    {
        return match ($this->type) {
            FiscalCharacteristicsImpactType::Delete => true,

            FiscalCharacteristicsImpactType::AdjustEffectiveTo => $this->isShrinkingEffectiveTo(),

            FiscalCharacteristicsImpactType::AdjustEffectiveFrom => $this->isShrinkingEffectiveFrom(),
        };
    }

    /**
     * Decide whether the new upper bound is strictly smaller than the
     * current one (open-ended counts as shrinking when a concrete value
     * is set).
     */
    private function isShrinkingEffectiveTo(): bool
    {
        if ($this->newEffectiveTo === null) {
            return false;
        }

        if ($this->targetEffectiveTo === null) {
            return true;
        }

        return $this->newEffectiveTo->lessThan(
            CarbonImmutable::parse($this->targetEffectiveTo),
        );
    }

    /**
     * Decide whether the new lower bound is strictly later than the
     * current one (i.e. shrinks the period from the left).
     */
    private function isShrinkingEffectiveFrom(): bool
    {
        if ($this->newEffectiveFrom === null) {
            return false;
        }

        return $this->newEffectiveFrom->greaterThan(
            CarbonImmutable::parse($this->targetEffectiveFrom),
        );
    }

    /**
     * Return a French sentence describing the impact, ready to be stacked
     * in a user-facing message (toast info or modal confirmation).
     */
    public function describe(): string
    {
        $period = $this->formatPeriod($this->targetEffectiveFrom, $this->targetEffectiveTo);

        return match ($this->type) {
            FiscalCharacteristicsImpactType::Delete => "Suppression de la version {$period}",
            FiscalCharacteristicsImpactType::AdjustEffectiveTo => sprintf(
                'Date de fin de la version %s ramenée au %s',
                $period,
                $this->newEffectiveTo?->format('d/m/Y') ?? 'sans fin',
            ),
            FiscalCharacteristicsImpactType::AdjustEffectiveFrom => sprintf(
                'Date de début de la version %s ramenée au %s',
                $period,
                $this->newEffectiveFrom?->format('d/m/Y') ?? 'sans début',
            ),
        };
    }

    /**
     * Format a date range as a French human-readable string ("du …",
     * "depuis le …").
     */
    private function formatPeriod(string $from, ?string $to): string
    {
        $fromFr = CarbonImmutable::parse($from)->format('d/m/Y');

        if ($to === null) {
            return "depuis le {$fromFr}";
        }

        $toFr = CarbonImmutable::parse($to)->format('d/m/Y');

        return "du {$fromFr} au {$toFr}";
    }
}
