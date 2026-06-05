<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Data\Shared\YearScopeData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Overview-tab-only payload for the Company Show page · the heavy
 * multi-year fiscal aggregation (current-year KPIs, lifetime totals,
 * historical evolution, per-year activity). Served eagerly only when
 * `?tab=overview`, otherwise `Inertia::optional` so the other tabs do
 * not pay this computation when loaded directly (tab-gating, "chargement
 * strict par écran"). The slim identity/base lives on
 * {@see CompanyDetailData}, always eager (header + every tab).
 */
#[TypeScript]
final class CompanyOverviewData extends Data
{
    /**
     * @param  list<CompanyYearStatsData>  $history  One entry per past year (current year excluded).
     * @param  list<CompanyActivityYearData>  $activityByYear  Visual detail per historical year.
     * @param  list<int>  $availableYears  Years with >= 1 contract for this company specifically.
     */
    public function __construct(
        public CompanyLifetimeStatsData $lifetime,
        public CompanyYearStatsData $kpiStats,
        public int $kpiYear,
        public bool $kpiFiscalAvailable,
        #[DataCollectionOf(CompanyYearStatsData::class)]
        public array $history,
        #[DataCollectionOf(CompanyActivityYearData::class)]
        public array $activityByYear,
        public array $availableYears,
        public YearScopeData $yearScope,
    ) {}
}
