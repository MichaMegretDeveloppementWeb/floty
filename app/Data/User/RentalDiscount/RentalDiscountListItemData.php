<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use App\Models\RentalDiscount;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Item de listing condensé pour ·
 *  - la table Index `/rental-discounts` (Lot 4)
 *  - la section "Réductions commerciales" intégrée à l'onglet
 *    Facturation de la page Show Company (Lot 3)
 *
 * Sert d'aperçu cliquable (lien Show construit par le front · le DTO
 * expose `id` pour cela).
 *
 * Volontairement slim · pas de détail véhicule (la section affiche un
 * badge "Tous" ou "N véhicules" basé sur `vehiclesCount` ;
 * `isAllVehicles` distingue les 2 sémantiques). L'identité entreprise
 * est toujours embarquée pour permettre l'affichage de la cellule
 * Entreprise sur l'Index (sur Company Show la cellule est cachée car
 * redondante avec le contexte).
 */
#[TypeScript]
final class RentalDiscountListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public string $companyColor,
        /** ISO Y-m-d. */
        public string $startDate,
        /** ISO Y-m-d. */
        public string $endDate,
        public int $discountBasisPoints,
        public ?string $label,
        public int $vehiclesCount,
        /**
         * `true` ssi la liste véhicules est vide en base (= applique à
         * tous les véhicules de l'entreprise sur la période · sémantique
         * applicative décodée par {@see App\Services\Billing\Discount\DiscountResolver}).
         */
        public bool $isAllVehicles,
        /** `'planned' | 'active' | 'expired'` calculé vs. la date du jour. */
        public string $status,
    ) {}

    public static function fromModel(RentalDiscount $discount, ?CarbonImmutable $today = null): self
    {
        $today ??= CarbonImmutable::now()->startOfDay();
        $start = CarbonImmutable::parse($discount->start_date->toDateString());
        $end = CarbonImmutable::parse($discount->end_date->toDateString());

        $status = match (true) {
            $today->lessThan($start) => 'planned',
            $today->greaterThan($end) => 'expired',
            default => 'active',
        };

        $vehiclesCount = $discount->relationLoaded('vehicles')
            ? $discount->vehicles->count()
            : $discount->vehicles()->count();

        return new self(
            id: $discount->id,
            companyId: $discount->company_id,
            companyShortCode: $discount->company->short_code,
            companyLegalName: $discount->company->legal_name,
            companyColor: $discount->company->color->value,
            startDate: $discount->start_date->toDateString(),
            endDate: $discount->end_date->toDateString(),
            discountBasisPoints: $discount->discount_basis_points,
            label: $discount->label,
            vehiclesCount: $vehiclesCount,
            isAllVehicles: $vehiclesCount === 0,
            status: $status,
        );
    }
}
