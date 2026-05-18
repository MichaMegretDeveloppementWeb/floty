<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Identité minimale d'un véhicule ciblé par une réduction commerciale.
 * Suffit pour afficher une plaque + marque/modèle dans la liste Show
 * et alimenter un lien vers la fiche véhicule (Lot 4 chantier
 * RentalDiscount).
 */
#[TypeScript]
final class RentalDiscountVehicleTagData extends Data
{
    public function __construct(
        public int $id,
        public string $licensePlate,
        public string $brand,
        public string $model,
    ) {}
}
