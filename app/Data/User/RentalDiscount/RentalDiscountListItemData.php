<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use App\Enums\Company\CompanyColor;
use App\Models\RentalDiscount;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Listing row for:
 *  - the `/rental-discounts` index table
 *  - the "Réductions commerciales" section in the Company Show Billing tab
 *
 * Acts as a clickable preview (the front-end builds the Show link from `id`).
 *
 * Intentionally slim: no vehicle detail (the section renders a "Tous" /
 * "N véhicules" badge from `vehiclesCount`; `isAllVehicles` distinguishes
 * the two semantics). Company identity is always embedded so the index
 * can render the Company cell; Company Show hides it as redundant with
 * the context.
 */
#[TypeScript]
final class RentalDiscountListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public CompanyColor $companyColor,
        /** ISO Y-m-d. */
        public string $startDate,
        /** ISO Y-m-d. */
        public string $endDate,
        public int $discountBasisPoints,
        public ?string $label,
        public int $vehiclesCount,
        /**
         * `true` iff the vehicle list is empty in DB (= applies to every
         * vehicle of the company over the period; applicative semantics
         * decoded by {@see App\Services\Billing\Discount\DiscountResolver}).
         */
        public bool $isAllVehicles,
        /** `'planned' | 'active' | 'expired'` computed vs. today. */
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
            companyColor: $discount->company->color,
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
