<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use App\Models\RentalDiscount;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full detail of a commercial discount for the Show page.
 *
 * Includes business identity + attached company + targeted vehicles +
 * computed status + creator. Application stats (active contract count,
 * issued invoices) are loaded separately to keep the cost explicit.
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
         * Vehicles explicitly targeted by the discount; empty means
         * "applies to every vehicle of the company over the period"
         * (decoded by `DiscountResolver`).
         *
         * @var array<int, RentalDiscountVehicleTagData>
         */
        #[DataCollectionOf(RentalDiscountVehicleTagData::class)]
        public array $vehicles,
        public bool $isAllVehicles,
        /** `'planned' | 'active' | 'expired'` computed vs. today. */
        public string $status,
        public ?string $createdByUserName,
        /** ISO Y-m-d, never null (timestamps non-nullable in DB). */
        public string $createdAt,
        /** ISO Y-m-d or null if never modified after creation. */
        public ?string $updatedAt,
        /** ISO Y-m-d or null if not soft-deleted. */
        public ?string $deletedAt,
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
        );
    }
}
