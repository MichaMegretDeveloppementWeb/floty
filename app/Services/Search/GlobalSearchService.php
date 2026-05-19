<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\User\Search\GlobalSearchCompanyItemData;
use App\Data\User\Search\GlobalSearchContractShortcutData;
use App\Data\User\Search\GlobalSearchDeclarationItemData;
use App\Data\User\Search\GlobalSearchDriverItemData;
use App\Data\User\Search\GlobalSearchResultData;
use App\Data\User\Search\GlobalSearchVehicleItemData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\Company;
use App\Models\Driver;
use App\Models\FiscalDeclaration;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Global search service powering the ⌘K palette.
 *
 * Pipeline · tokenize the query → five parallel searches, two of them
 * conditional ·
 *
 *   - Vehicles · `CONCAT_WS(' ', brand, model, license_plate)` LIKE
 *     every token (AND).
 *   - Companies · `CONCAT_WS(' ', legal_name, siren)` LIKE every
 *     token (AND).
 *   - Drivers · `CONCAT_WS(' ', first_name, last_name)` LIKE every
 *     token (AND).
 *   - Contract shortcuts (conditional) · enabled iff ≥ 2 tokens. Each
 *     token must match either the vehicle or the company side (OR per
 *     token, AND across tokens). `GROUP BY (vehicle_id, company_id,
 *     year)`.
 *   - Declarations (conditional) · enabled iff the query contains a
 *     year (regex `\b(20\d{2})\b`) and at least one remaining token
 *     after removing the year. Looks up companies matching the tokens
 *     then the latest active version (`is_obsolete = false`) of the
 *     `(company, year)` pair.
 *
 * Implemented with MySQL `LIKE %token%` (case-insensitive via the
 * default `utf8mb4_unicode_ci` collation). At Floty volumes (~5k
 * vehicles, ~500 companies, ~10k contracts), sub-50 ms even without
 * FULLTEXT indexes. Tokens are bound as parameters; `%` / `_` are
 * escaped through {@see escapeLikeWildcards()}.
 */
final class GlobalSearchService
{
    private const LIMIT_PER_GROUP = 5;

    private const YEAR_REGEX = '/\b(20\d{2})\b/';

    public function searchAll(string $query): GlobalSearchResultData
    {
        $tokens = $this->tokenize($query);

        if (count($tokens) === 0) {
            return GlobalSearchResultData::emptyResult($query);
        }

        [$year, $nonYearTokens] = $this->extractYear($tokens);

        $vehicles = $this->searchVehicles($tokens);
        $companies = $this->searchCompanies($tokens);
        $drivers = $this->searchDrivers($tokens);

        $contractShortcuts = [];

        if (count($tokens) >= 2) {
            $contractShortcuts = $this->searchContractShortcuts($tokens);
        }

        $declarations = [];

        if ($year !== null && count($nonYearTokens) >= 1) {
            $declarations = $this->searchDeclarations($nonYearTokens, $year);
        }

        return new GlobalSearchResultData(
            query: $query,
            vehicles: $vehicles,
            companies: $companies,
            drivers: $drivers,
            contractShortcuts: $contractShortcuts,
            declarations: $declarations,
        );
    }

    /**
     * Splits the query on whitespace, lowercases, drops empties.
     *
     * @return list<string>
     */
    private function tokenize(string $query): array
    {
        $tokens = preg_split('/\s+/', mb_strtolower(trim($query)));

        if ($tokens === false) {
            return [];
        }

        return array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));
    }

    /**
     * Extracts the first 20XX year token (regex `\b(20\d{2})\b`) and
     * returns the remaining tokens. `[null, $tokens]` when no year
     * is found.
     *
     * @param  list<string>  $tokens
     * @return array{0: int|null, 1: list<string>}
     */
    private function extractYear(array $tokens): array
    {
        $year = null;
        $remaining = [];

        foreach ($tokens as $token) {
            if ($year === null && preg_match(self::YEAR_REGEX, $token, $matches) === 1) {
                $year = (int) $matches[1];

                continue;
            }

            $remaining[] = $token;
        }

        return [$year, $remaining];
    }

    /**
     * Escapes SQL LIKE wildcards (`%`, `_`) and the backslash in a
     * user-supplied token. Combine with `%` on each side for a safe
     * "contains" match.
     */
    private function escapeLikeWildcards(string $token): string
    {
        return addcslashes($token, '%_\\');
    }

    /**
     * Adds a `WHERE CONCAT_WS(' ', ...cols) LIKE ?` per token, ANDed.
     * Case-insensitive on the default MySQL collation.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @param  list<string>  $tokens
     */
    private function applyTokensFilter(Builder $query, array $columns, array $tokens): void
    {
        $concatExpr = sprintf('CONCAT_WS(\' \', %s)', implode(', ', $columns));

        foreach ($tokens as $token) {
            $escaped = $this->escapeLikeWildcards($token);
            $query->whereRaw($concatExpr.' LIKE ?', ['%'.$escaped.'%']);
        }
    }

    /**
     * @param  list<string>  $tokens
     * @return list<GlobalSearchVehicleItemData>
     */
    private function searchVehicles(array $tokens): array
    {
        $query = Vehicle::query();
        $this->applyTokensFilter($query, ['brand', 'model', 'license_plate'], $tokens);

        $vehicles = $query
            ->orderBy('brand')
            ->orderBy('model')
            ->limit(self::LIMIT_PER_GROUP)
            ->get();

        $items = [];

        foreach ($vehicles as $vehicle) {
            $items[] = new GlobalSearchVehicleItemData(
                id: $vehicle->id,
                label: sprintf('%s %s · %s', $vehicle->brand, $vehicle->model, $vehicle->license_plate),
                sublabel: $vehicle->exit_date !== null
                    ? sprintf('Sorti le %s', $vehicle->exit_date->format('d/m/Y'))
                    : null,
                href: route('user.vehicles.show', ['vehicle' => $vehicle->id]),
            );
        }

        return $items;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<GlobalSearchCompanyItemData>
     */
    private function searchCompanies(array $tokens): array
    {
        $query = Company::query();
        $this->applyTokensFilter($query, ['legal_name', 'siren'], $tokens);

        $companies = $query
            ->orderBy('legal_name')
            ->limit(self::LIMIT_PER_GROUP)
            ->get();

        $items = [];

        foreach ($companies as $company) {
            $sublabel = null;

            if ($company->siren !== null && $company->siren !== '') {
                $sublabel = 'SIREN '.$this->formatSiren($company->siren);
            } elseif ($company->city !== null && $company->city !== '') {
                $sublabel = $company->city;
            }

            $items[] = new GlobalSearchCompanyItemData(
                id: $company->id,
                label: $company->legal_name,
                sublabel: $sublabel,
                href: route('user.companies.show', ['company' => $company->id]),
            );
        }

        return $items;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<GlobalSearchDriverItemData>
     */
    private function searchDrivers(array $tokens): array
    {
        $query = Driver::query()
            ->with([
                // Active companies only (`left_at IS NULL`) for the
                // sublabel · single eager-loaded query, no N+1.
                'companies' => static function ($q): void {
                    $q->wherePivotNull('left_at')
                        ->select('companies.id', 'companies.legal_name');
                },
            ]);
        $this->applyTokensFilter($query, ['first_name', 'last_name'], $tokens);

        $drivers = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::LIMIT_PER_GROUP)
            ->get();

        $items = [];

        foreach ($drivers as $driver) {
            $companyNames = $driver->companies
                ->pluck('legal_name')
                ->filter()
                ->take(3)
                ->implode(', ');

            $items[] = new GlobalSearchDriverItemData(
                id: $driver->id,
                label: $driver->full_name,
                sublabel: $companyNames !== '' ? $companyNames : null,
                href: route('user.drivers.show', ['driver' => $driver->id]),
            );
        }

        return $items;
    }

    /**
     * Finds `(vehicle, company, year)` triplets with at least one
     * contract, where the combination matches every token (each token
     * must match the vehicle or the company side).
     *
     * Per-year granularity (`YEAR(start_date)`) · without it, clicking
     * a shortcut without a year filter landed on the Contracts Index
     * default year, which could hide the pair's contracts when on a
     * different year. One shortcut per `(vehicle, company, year)` ·
     * ordered by count DESC then year DESC.
     *
     * Implemented as a `DB::table()` aggregate (SQL aliases not
     * exposed on the Eloquent model) so PHPStan does not flag
     * `property.notFound`. No N+1 · single query with GROUP BY and
     * `MAX()` to fetch the display labels.
     *
     * @param  list<string>  $tokens
     * @return list<GlobalSearchContractShortcutData>
     */
    private function searchContractShortcuts(array $tokens): array
    {
        $query = DB::table('contracts')
            ->join('vehicles', 'vehicles.id', '=', 'contracts.vehicle_id')
            ->join('companies', 'companies.id', '=', 'contracts.company_id')
            ->whereNull('contracts.deleted_at')
            ->whereNull('vehicles.deleted_at')
            ->whereNull('companies.deleted_at')
            ->select([
                'contracts.vehicle_id',
                'contracts.company_id',
                DB::raw('YEAR(contracts.start_date) as start_year'),
                DB::raw('COUNT(*) as contract_count'),
                DB::raw('MAX(vehicles.brand) as vehicle_brand'),
                DB::raw('MAX(vehicles.model) as vehicle_model'),
                DB::raw('MAX(vehicles.license_plate) as vehicle_plate'),
                DB::raw('MAX(companies.legal_name) as company_name'),
            ])
            ->groupBy(
                'contracts.vehicle_id',
                'contracts.company_id',
                DB::raw('YEAR(contracts.start_date)'),
            );

        $vehicleConcat = "CONCAT_WS(' ', vehicles.brand, vehicles.model, vehicles.license_plate)";
        $companyConcat = "CONCAT_WS(' ', companies.legal_name, companies.siren)";

        foreach ($tokens as $token) {
            $escaped = $this->escapeLikeWildcards($token);
            // Each token must match the vehicle side or the company
            // side · OR per token, AND across tokens.
            $query->whereRaw(
                "($vehicleConcat LIKE ? OR $companyConcat LIKE ?)",
                ['%'.$escaped.'%', '%'.$escaped.'%'],
            );
        }

        $rows = $query
            ->orderByDesc('contract_count')
            ->orderByDesc('start_year')
            ->orderBy('contracts.vehicle_id')
            ->limit(self::LIMIT_PER_GROUP)
            ->get();

        $items = [];

        foreach ($rows as $row) {
            $vehicleLabel = sprintf(
                '%s %s %s',
                (string) $row->vehicle_brand,
                (string) $row->vehicle_model,
                (string) $row->vehicle_plate,
            );
            $count = (int) $row->contract_count;
            $year = (int) $row->start_year;

            $items[] = new GlobalSearchContractShortcutData(
                vehicleId: (int) $row->vehicle_id,
                companyId: (int) $row->company_id,
                year: $year,
                label: sprintf('%s · chez %s', $vehicleLabel, (string) $row->company_name),
                sublabel: sprintf('%d contrat%s en %d', $count, $count > 1 ? 's' : '', $year),
                count: $count,
                href: route('user.contracts.index', [
                    'vehicleId' => $row->vehicle_id,
                    'companyId' => $row->company_id,
                    'year' => $year,
                ]),
            );
        }

        return $items;
    }

    /**
     * Active declarations (`is_obsolete = false`) for the
     * `(company matching tokens, year)` pair. One row per
     * `(company, year)`.
     *
     * @param  list<string>  $tokens
     * @return list<GlobalSearchDeclarationItemData>
     */
    private function searchDeclarations(array $tokens, int $year): array
    {
        $companyQuery = Company::query();
        $this->applyTokensFilter($companyQuery, ['legal_name', 'siren'], $tokens);
        $companyIds = $companyQuery->limit(self::LIMIT_PER_GROUP * 2)->pluck('id')->all();

        if (count($companyIds) === 0) {
            return [];
        }

        $declarations = FiscalDeclaration::query()
            ->with(['company:id,legal_name'])
            ->whereIn('company_id', $companyIds)
            ->where('fiscal_year', $year)
            ->where('is_obsolete', false)
            ->orderByDesc('created_at')
            ->limit(self::LIMIT_PER_GROUP)
            ->get();

        $items = [];

        foreach ($declarations as $declaration) {
            $items[] = new GlobalSearchDeclarationItemData(
                id: $declaration->id,
                label: sprintf('%s · Déclaration %d', $declaration->company->legal_name, $declaration->fiscal_year),
                sublabel: $this->formatDeclarationStatus($declaration),
                href: route('user.declarations.show', ['declaration' => $declaration->id]),
            );
        }

        return $items;
    }

    /**
     * Human label for a declaration status, used in the search
     * palette sublabel.
     */
    private function formatDeclarationStatus(FiscalDeclaration $declaration): string
    {
        return match ($declaration->status) {
            FiscalDeclarationStatus::Generated => $declaration->generated_at !== null
                ? sprintf('Générée le %s', $declaration->generated_at->format('d/m/Y'))
                : 'Générée',
            FiscalDeclarationStatus::Deferred => 'Reportée',
            FiscalDeclarationStatus::Draft => 'Brouillon',
        };
    }

    /**
     * Formats a raw 9-digit SIREN into 3-digit groups (« 123 456 789 »).
     */
    private function formatSiren(string $siren): string
    {
        if (strlen($siren) !== 9) {
            return $siren;
        }

        return substr($siren, 0, 3).' '.substr($siren, 3, 3).' '.substr($siren, 6, 3);
    }
}
