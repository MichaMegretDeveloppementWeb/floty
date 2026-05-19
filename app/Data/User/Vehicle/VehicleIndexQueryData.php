<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\VehicleStatus;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Input DTO for the server-side Vehicles Index (ADR-0020).
 *
 * `includeExited` defaults to true so historical vehicles remain consultable
 * and editable (ADR-0018 § 4). Sort whitelist excludes `fullYearTax` because
 * it is computed by the fiscal aggregator and not orderable in pure SQL
 * (ADR-0020 D6).
 */
#[TypeScript]
final class VehicleIndexQueryData extends IndexQueryData
{
    public function __construct(
        public bool $includeExited = true,
        public ?VehicleStatus $status = null,
        public ?EnergySource $energySource = null,
        public ?PollutantCategory $pollutantCategory = null,
        public ?bool $handicapAccess = null,
        public ?int $firstRegistrationYearMin = null,
        public ?int $firstRegistrationYearMax = null,
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
        return [
            'licensePlate',
            'model',
            'firstFrenchRegistrationDate',
            'acquisitionDate',
            'currentStatus',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $energyValues = array_map(static fn (EnergySource $e): string => $e->value, EnergySource::cases());
        $pollutantValues = array_map(static fn (PollutantCategory $p): string => $p->value, PollutantCategory::cases());
        // Free calendar range; year resolution against the dynamic contract scope
        // is handled by the controller via `AvailableYearsResolver`. The fiscal
        // aggregator tolerates years without rules (returns 0 €).
        $yearRule = ['nullable', 'integer', 'min:1900', 'max:2100'];

        return array_merge(parent::rules(), [
            'includeExited' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,maintenance,sold,destroyed,other'],
            'energySource' => ['nullable', 'string', 'in:'.implode(',', $energyValues)],
            'pollutantCategory' => ['nullable', 'string', 'in:'.implode(',', $pollutantValues)],
            'handicapAccess' => ['nullable', 'boolean'],
            'firstRegistrationYearMin' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'firstRegistrationYearMax' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'year' => $yearRule,
        ]);
    }
}
