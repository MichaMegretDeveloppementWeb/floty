<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Slim, always-eager base for the Company Show page · identity hero +
 * status flags + drivers list (consumed by the header on every tab and
 * by the Drivers tab) + cheap counters. The heavy multi-year fiscal
 * aggregation (KPIs, lifetime, history, activity) lives in
 * {@see CompanyOverviewData}, served only on the overview tab (tab-gating).
 *
 * `currentRealYear` stays here: the Contracts tab derives its default
 * period from it.
 */
#[TypeScript]
final class CompanyDetailData extends Data
{
    /**
     * @param  list<CompanyDriverRowData>  $drivers
     */
    public function __construct(
        public int $id,
        public string $legalName,
        public string $shortCode,
        public CompanyColor $color,
        public ?string $siren,
        public ?string $siret,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $postalCode,
        public ?string $city,
        public string $country,
        public ?string $contactName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public bool $isActive,
        public bool $isOig,
        public bool $isIndividualBusiness,
        public int $contractsCount,
        public int $activeDriversCount,
        public int $totalDriversCount,
        #[DataCollectionOf(CompanyDriverRowData::class)]
        public array $drivers,
        public int $currentRealYear,
    ) {}
}
