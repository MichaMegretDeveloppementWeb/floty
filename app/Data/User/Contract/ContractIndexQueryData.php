<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Query input for the server-side Contracts index (ADR-0020).
 *
 * Filters:
 *  - `vehicleId`, `companyId`, `driverId`: exact FK match
 *  - `type` (lcd|lld): exact enum match
 *  - `year`: full-year mode, mutually exclusive with custom range. When
 *    set without a custom range, derives `periodStart=YYYY-01-01,
 *    periodEnd=YYYY-12-31` before SQL filtering.
 *  - `periodStart` + `periodEnd` (Y-m-d): overlap filter
 *    (`start_date <= periodEnd AND end_date >= periodStart`).
 *
 * Allowed sort keys: `vehicle | company | startDate | endDate | duration |
 * type` (all translatable to pure SQL).
 */
#[TypeScript]
final class ContractIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?int $vehicleId = null,
        public ?int $companyId = null,
        public ?int $driverId = null,
        public ?string $type = null,
        public ?int $year = null,
        public ?string $periodStart = null,
        public ?string $periodEnd = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $search = null,
        ?string $sortKey = null,
        SortDirection $sortDirection = SortDirection::Asc,
    ) {
        parent::__construct($page, $perPage, $search, $sortKey, $sortDirection);
    }

    public static function allowedSortKeys(): array
    {
        return ['vehicle', 'company', 'startDate', 'endDate', 'duration', 'type'];
    }

    public static function rules(): array
    {
        // Business data is decoupled from fiscal rule coverage: the year is
        // accepted freely (mirrors VehicleIndexQueryData).
        $yearRule = ['nullable', 'integer', 'min:1900', 'max:2100'];

        return array_merge(parent::rules(), [
            'vehicleId' => ['nullable', 'integer', 'exists:vehicles,id'],
            'companyId' => ['nullable', 'integer', 'exists:companies,id'],
            'driverId' => ['nullable', 'integer', 'exists:drivers,id'],
            'type' => ['nullable', 'string', 'in:lcd,lld'],
            'year' => $yearRule,
            'periodStart' => ['nullable', 'date_format:Y-m-d'],
            'periodEnd' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:periodStart'],
        ]);
    }

    /**
     * Resolve the effective period. Priority:
     *   1. Custom `periodStart`/`periodEnd` range when present (preserves a
     *      tab-level custom filter when the parent page also passes `year`).
     *   2. Full fiscal year derived from `year`.
     *   3. No period filter.
     *
     * @return array{periodStart: ?string, periodEnd: ?string}
     */
    public function effectivePeriod(): array
    {
        if ($this->periodStart !== null || $this->periodEnd !== null) {
            return [
                'periodStart' => $this->periodStart,
                'periodEnd' => $this->periodEnd,
            ];
        }

        if ($this->year !== null) {
            return [
                'periodStart' => sprintf('%d-01-01', $this->year),
                'periodEnd' => sprintf('%d-12-31', $this->year),
            ];
        }

        return [
            'periodStart' => null,
            'periodEnd' => null,
        ];
    }
}
