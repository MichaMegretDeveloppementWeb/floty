<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Option véhicule **minimale** pour les sélecteurs UI · dropdown
 * SearchableSelect dans le FilterPopover des pages Index, sélecteur
 * véhicule du formulaire Create/Edit Contract, chips de filtre actif.
 * Ne porte AUCUN calcul fiscal · zéro pipeline run.
 *
 * Le calcul de taxe pleine année pour un véhicule sélectionné
 * (anciennement éager via un `fullYearTaxByYear` lourd) est désormais
 * déclenché à la volée via l'endpoint AJAX
 * `GET /app/vehicles/{vehicle}/full-year-tax` (composable frontend
 * `useVehicleFullYearTax`) quand l'utilisateur change effectivement
 * de véhicule ou de date dans le drawer Contract.
 *
 * **Doctrine** · méthodes dédiées par usage + on-demand sur
 * interaction. Pas de coût caché pour un champ jamais consommé
 * (audit perf 2026-05-16, cause C-3).
 *
 * @see VehicleListingService::listForLightSelector()
 * @see VehicleListingService::fullYearTaxForVehicle()
 */
#[TypeScript]
final class VehicleFilterOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $licensePlate,
        /** Format affiché dans le dropdown · « AB-123-CD - Marque Modèle ». */
        public string $label,
        /** Vrai si le véhicule est sorti (`exit_date IS NOT NULL`). */
        public bool $isExited,
        /** Date de sortie ISO `Y-m-d` ou null. */
        public ?string $exitDate,
    ) {}
}
