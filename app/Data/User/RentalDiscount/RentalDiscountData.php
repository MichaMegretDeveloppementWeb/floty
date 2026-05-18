<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use App\Models\RentalDiscount;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail complet d'une réduction commerciale pour la page Show
 * (Lot 4 du chantier RentalDiscount).
 *
 * Inclut · identité métier + entreprise rattachée + liste véhicules
 * ciblés + status calculé + créateur. Les statistiques d'application
 * (nombre de contrats actifs, factures émises) sont chargées séparément
 * pour rester explicites sur le coût.
 */
#[TypeScript]
final class RentalDiscountData extends Data
{
    /**
     * @param  array<int, RentalDiscountVehicleTagData>  $vehicles
     */
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
        public ?string $notes,
        /**
         * Véhicules ciblés explicitement par la réduction (peut être vide
         * = applique à tous les véhicules de l'entreprise sur la période,
         * sémantique applicative décodée par `DiscountResolver`).
         *
         * @var array<int, RentalDiscountVehicleTagData>
         */
        #[DataCollectionOf(RentalDiscountVehicleTagData::class)]
        public array $vehicles,
        public bool $isAllVehicles,
        /** `'planned' | 'active' | 'expired'` calculé vs. la date du jour. */
        public string $status,
        public ?string $createdByUserName,
        /** ISO Y-m-d, jamais null (timestamps non nullable en base). */
        public string $createdAt,
        /** ISO Y-m-d ou null si jamais modifiée après création. */
        public ?string $updatedAt,
        /** ISO Y-m-d ou null si non soft-deletée. */
        public ?string $deletedAt,
        /**
         * Nombre de factures émises (active ou obsolète) qui ont appliqué
         * cette réduction sur au moins une ligne · alimente le compteur
         * « N factures concernées » de la carte Application sur Show.
         */
        public int $invoiceLinesCount,
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

        $vehicles = $discount->relationLoaded('vehicles')
            ? $discount->vehicles
                ->map(fn ($v): RentalDiscountVehicleTagData => new RentalDiscountVehicleTagData(
                    id: $v->id,
                    licensePlate: $v->license_plate,
                    brand: $v->brand,
                    model: $v->model,
                ))
                ->all()
            : [];

        $invoiceLinesCount = $discount->relationLoaded('invoiceLines')
            ? $discount->invoiceLines->count()
            : $discount->invoiceLines()->count();

        $createdByName = null;
        if ($discount->relationLoaded('createdBy') && $discount->createdBy !== null) {
            $createdByName = trim($discount->createdBy->first_name.' '.$discount->createdBy->last_name);
        }

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
            notes: $discount->notes,
            vehicles: $vehicles,
            isAllVehicles: count($vehicles) === 0,
            status: $status,
            createdByUserName: $createdByName !== '' ? $createdByName : null,
            createdAt: $discount->created_at?->toDateString() ?? '',
            updatedAt: $discount->updated_at?->toDateString(),
            deletedAt: $discount->deleted_at?->toDateString(),
            invoiceLinesCount: $invoiceLinesCount,
        );
    }
}
