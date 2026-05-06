<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Models\VehicleYearlyPricing;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload entrée HTTP / sortie JSON pour les tarifs jour/semaine/mois
 * d'un véhicule sur une année donnée.
 *
 * **Convention monétaire** : tarifs en cents (1 € = 100), évite les
 * imprécisions float (cf. mémoire projet sur les calculs fiscaux exacts).
 * La conversion en euros pour affichage se fait côté frontend.
 *
 * **Validation** :
 *   - Année dans [2020, 2099] (cohérence avec CHECK SQL côté table)
 *   - Tarifs ≥ 0 (zéro autorisé pour les véhicules en usage gratuit
 *     internes, ex. véhicule de courtoisie)
 *   - Tarifs ≤ 99_999_999 cents (~999 999 €/jour, largement au-dessus
 *     du réel mais évite l'overflow de unsignedInteger en base)
 *
 * Le DTO sert pour :
 *   - L'entrée HTTP via `VehicleYearlyPricingController::store`
 *   - La sortie JSON exposée à `VehicleData` (chantier 14.B)
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class VehicleYearlyPricingData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(2020), Max(2099)]
        public int $year,

        #[Required, IntegerType, Min(0), Max(99_999_999)]
        public int $dailyRateCents,

        #[Required, IntegerType, Min(0), Max(99_999_999)]
        public int $weeklyRateCents,

        #[Required, IntegerType, Min(0), Max(99_999_999)]
        public int $monthlyRateCents,
    ) {}

    /**
     * Hydrate un DTO depuis un model Eloquent. Utilisé par les couches
     * de présentation qui exposent les pricings à un parent (ex. `VehicleData`).
     */
    public static function fromModel(VehicleYearlyPricing $pricing): self
    {
        return new self(
            year: $pricing->year,
            dailyRateCents: $pricing->daily_rate_cents,
            weeklyRateCents: $pricing->weekly_rate_cents,
            monthlyRateCents: $pricing->monthly_rate_cents,
        );
    }
}
