<?php

declare(strict_types=1);

namespace App\Services\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureReadRepositoryInterface;

/**
 * Decides whether a set of event natures makes the event fiscally reductive:
 * true as soon as ONE nature matches a label of the frozen reductive block
 * (`vehicle_event_natures.is_fiscally_reductive`). Free entries and every
 * other nature are non-reductive by default.
 *
 * Matching is trimmed and case-insensitive, the same normalisation as the
 * nature list composition ({@see App\Support\VehicleEvent\EventCategoryList}).
 * The result feeds the write-time denormalisation of
 * `vehicle_events.has_fiscal_impact`; the fiscal rules (R-20XX-008) keep
 * reading that frozen boolean only.
 */
final readonly class EventNatureFiscalResolver
{
    public function __construct(
        private VehicleEventNatureReadRepositoryInterface $natures,
    ) {}

    /**
     * @param  list<string>  $natures
     */
    public function hasReductiveNature(array $natures): bool
    {
        if ($natures === []) {
            return false;
        }

        $reductive = array_map(
            static fn (string $label): string => mb_strtolower(trim($label)),
            $this->natures->reductiveLabels(),
        );

        foreach ($natures as $nature) {
            if (in_array(mb_strtolower(trim($nature)), $reductive, true)) {
                return true;
            }
        }

        return false;
    }
}
