<?php

declare(strict_types=1);

namespace App\Services\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureReadRepositoryInterface;
use App\Support\VehicleEvent\EventNatureCatalog;

/**
 * True when one nature matches the frozen reductive block (trim +
 * case-insensitive). Matches the UNION of the DB rows and
 * {@see EventNatureCatalog::REDUCTIVE} so fiscal correctness never depends
 * on the seeder having run; feeds the frozen `has_fiscal_impact` boolean.
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
            [...$this->natures->reductiveLabels(), ...EventNatureCatalog::REDUCTIVE],
        );

        foreach ($natures as $nature) {
            if (in_array(mb_strtolower(trim($nature)), $reductive, true)) {
                return true;
            }
        }

        return false;
    }
}
