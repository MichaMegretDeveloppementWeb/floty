<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use App\Exceptions\BaseAppException;

/**
 * Levée par {@see App\Services\Billing\BillingCalculator} quand un ou
 * plusieurs véhicules présents sur la période n'ont pas de tarif
 * `VehicleYearlyPricing` défini pour l'année concernée.
 *
 * **Politique UX** : on collecte l'**ensemble** des véhicules manquants
 * en un seul passage, plutôt que de stopper au premier ; l'utilisateur
 * voit d'un seul coup d'œil tout ce qu'il doit renseigner.
 *
 * @phpstan-type MissingItem array{vehicleId: int, licensePlate: string, year: int}
 */
final class MissingPricingException extends BaseAppException
{
    /**
     * Limite l'énumération des plaques dans le message UX pour rester
     * lisible quand de nombreux véhicules sont concernés. Au-delà, on
     * suffixe « (et N autres) » (T11 / E.15).
     */
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
     * Cape la liste à `USER_MESSAGE_PLATES_CAP` plaques et suffixe
     * `(et N autres)` si nécessaire. Le message technique conserve
     * la liste complète pour le debug.
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
     * @param  list<MissingItem>  $missing
     */
    public static function forMissingItems(array $missing): self
    {
        return new self($missing);
    }
}
