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
     * @param  list<MissingItem>  $missing
     */
    public function __construct(
        public readonly array $missing,
    ) {
        $count = count($missing);
        $plates = implode(', ', array_map(static fn (array $m): string => $m['licensePlate'], $missing));
        $year = $missing[0]['year'] ?? 0;

        parent::__construct(
            technicalMessage: "Missing yearly pricing for {$count} vehicle(s) in year {$year}: {$plates}",
            userMessage: $count === 1
                ? "Le tarif {$year} n'est pas renseigné sur le véhicule {$plates}. Renseignez-le sur la fiche véhicule pour pouvoir générer la facture."
                : "Le tarif {$year} n'est pas renseigné sur {$count} véhicules : {$plates}. Renseignez-les sur leurs fiches respectives pour pouvoir générer la facture.",
        );
    }

    /**
     * @param  list<MissingItem>  $missing
     */
    public static function forMissingItems(array $missing): self
    {
        return new self($missing);
    }
}
