<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Option véhicule **minimale** pour les sélecteurs de filtre (dropdown
 * SearchableSelect dans le FilterPopover des pages Index, chips de
 * filtre actif). Ne porte AUCUN calcul fiscal · zéro pipeline run.
 *
 * À distinguer de {@see VehicleOptionData} qui porte en plus
 * `fullYearTaxByYear` calculé par le pipeline fiscal · cette version
 * lourde est réservée aux formulaires Create/Edit de contrat qui
 * affichent une indication de coût annuel à la sélection véhicule.
 *
 * **Doctrine** · méthodes dédiées par usage. La page Index a besoin
 * d'identité + label pour le filtre, point. Pas de coût caché pour
 * un champ jamais consommé (audit perf 2026-05-16, cause C-3).
 *
 * @see VehicleListingService::listForFilterDropdown()
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
