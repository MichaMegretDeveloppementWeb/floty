<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use App\Exceptions\BaseAppException;

/**
 * One or more vehicles on the period lack a `VehicleYearlyPricing` row for the year.
 * Collects all missing vehicles in a single pass so the user sees the full list at once.
 *
 * @phpstan-type MissingItem array{vehicleId: int, licensePlate: string, year: int}
 */
final class MissingPricingException extends BaseAppException
{
    /** Caps the plate list in the user message; the technical message keeps the full list. */
    private const int USER_MESSAGE_PLATES_CAP = 5;

    /**
     * @param  list<MissingItem>  $missing
     */
    public function __construct(
        public readonly array $missing,
    ) {
        $count = count($missing);
        $allPlates = array_map(static fn (array $m): string => $m['licensePlate'], $missing);
        $year = $missing[0]['year'] ?? 0;

        $userPlatesList = $this->formatUserPlatesList($allPlates);

        parent::__construct(
            technicalMessage: "Missing yearly pricing for {$count} vehicle(s) in year {$year}: ".implode(', ', $allPlates),
            userMessage: $count === 1
                ? "Le tarif {$year} n'est pas renseigné sur le véhicule {$userPlatesList}. Renseignez-le sur la fiche véhicule pour pouvoir générer la facture."
                : "Le tarif {$year} n'est pas renseigné sur {$count} véhicules : {$userPlatesList}. Renseignez-les sur leurs fiches respectives pour pouvoir générer la facture.",
        );
    }

    /**
     * Caps the plate list at `USER_MESSAGE_PLATES_CAP` and appends a "(and N more)" suffix.
     *
     * @param  list<string>  $plates
     */
    private function formatUserPlatesList(array $plates): string
    {
        $total = count($plates);

        if ($total <= self::USER_MESSAGE_PLATES_CAP) {
            return implode(', ', $plates);
        }

        $shown = array_slice($plates, 0, self::USER_MESSAGE_PLATES_CAP);
        $excess = $total - self::USER_MESSAGE_PLATES_CAP;

        return implode(', ', $shown)." (et {$excess} autres)";
    }

    /**
     * Build the exception from a list of missing items.
     *
     * @param  list<MissingItem>  $missing
     */
    public static function forMissingItems(array $missing): self
    {
        return new self($missing);
    }
}
