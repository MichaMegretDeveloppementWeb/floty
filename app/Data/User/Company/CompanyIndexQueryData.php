<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Input DTO for the server-side Companies Index (ADR-0020).
 *
 * Sort whitelist excludes the computed `daysUsed` and `annualTaxDue`
 * (ADR-0020 D6: re-enable once materialised).
 */
#[TypeScript]
final class CompanyIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?bool $isActive = null,
        public ?string $contractsScope = null,
        public ?string $city = null,
        /**
         * Year driving the financial columns (daysUsed, annualTaxDue).
         * Resolved by the controller from current calendar year when null.
         */
        public ?int $year = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $search = null,
        ?string $sortKey = null,
        SortDirection $sortDirection = SortDirection::Asc,
    ) {
        parent::__construct($page, $perPage, $search, $sortKey, $sortDirection);
    }

    /**
     * @return list<string>
     */
    public static function allowedSortKeys(): array
    {
        return ['shortCode', 'legalName', 'siren', 'city'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        // Free calendar range; year resolution is performed downstream and
        // the fiscal aggregator tolerates years without rules (returns 0 €).
        $yearRule = ['nullable', 'integer', 'min:1900', 'max:2100'];

        return array_merge(parent::rules(), [
            'isActive' => ['nullable', 'boolean'],
            'contractsScope' => ['nullable', 'string', 'in:with,without'],
            'city' => ['nullable', 'string', 'max:255'],
            'year' => $yearRule,
        ]);
    }
}
