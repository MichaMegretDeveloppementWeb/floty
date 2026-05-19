<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row of the Companies Index table, with annual aggregates for the fleet.
 *
 * `annualTaxDue` and `rentalPriceTotal` are deferred-hydrated and rendered
 * with a skeleton until the `costs` Inertia::defer prop resolves.
 */
#[TypeScript]
final class CompanyListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $legalName,
        public string $shortCode,
        public CompanyColor $color,
        public ?string $siren,
        public ?string $city,
        public bool $isActive,
        public int $daysUsed,
        /** Null during deferred hydration. */
        public ?float $annualTaxDue = null,
        /** Null during deferred hydration OR when at least one vehicle has no yearly pricing. */
        public ?float $rentalPriceTotal = null,
    ) {}
}
