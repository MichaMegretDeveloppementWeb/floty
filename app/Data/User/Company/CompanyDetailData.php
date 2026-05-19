<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Data\Shared\YearScopeData;
use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Detailed view of a company. Feeds the Company Show page with three temporal
 * lenses: current-year KPIs (`kpiStats`), historical evolution (`history`)
 * and per-year exploration (`activityByYear`). Identity fields are intemporal.
 *
 * `yearScope` carries the global available years (ADR-0020). `lifetime` is
 * retained for backwards compatibility with potential consumers.
 */
#[TypeScript]
final class CompanyDetailData extends Data
{
    /**
     * @param  list<CompanyDriverRowData>  $drivers
     * @param  list<CompanyYearStatsData>  $history  One entry per past year with ≥ 1 contract (excludes current year).
     * @param  list<CompanyActivityYearData>  $activityByYear  Visual detail per historical year.
     * @param  list<int>  $availableYears  Years with ≥ 1 contract for this company specifically.
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
        public CompanyLifetimeStatsData $lifetime,
        public CompanyYearStatsData $kpiStats,
        public int $kpiYear,
        public bool $kpiFiscalAvailable,
        #[DataCollectionOf(CompanyYearStatsData::class)]
        public array $history,
        #[DataCollectionOf(CompanyActivityYearData::class)]
        public array $activityByYear,
        public array $availableYears,
        public int $currentRealYear,
        public YearScopeData $yearScope,
    ) {}
}
