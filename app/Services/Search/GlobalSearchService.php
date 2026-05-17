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
 * Service de recherche globale (palette ⌘K, V1.1).
 *
 * Pipeline · tokenize la query → 5 recherches en parallèle dont
 * 2 conditionnelles ·
 *
 *  - **Vehicles** · `CONCAT_WS(' ', brand, model, license_plate)`
 *    LIKE chaque token (AND).
 *  - **Companies** · `CONCAT_WS(' ', legal_name, siren)` LIKE chaque
 *    token (AND).
 *  - **Drivers** · `CONCAT_WS(' ', first_name, last_name)` LIKE chaque
 *    token (AND).
 *  - **Contracts shortcuts** (conditionnel) · activé ssi ≥ 2 tokens.
 *    Chaque token doit matcher soit côté véhicule, soit côté entreprise
 *    (logique OR par token, AND entre tokens). GROUP BY (vehicle_id,
 *    company_id) · 1 raccourci par couple effectivement contracté.
 *  - **Declarations** (conditionnel) · activé ssi la query contient
 *    une année (regex `\b(20\d{2})\b`) ET ≥ 1 token reste après retrait
 *    de l'année. Recherche entreprises avec ces tokens, puis dernière
 *    version active (`is_obsolete = false`) du couple (company, year).
 *
 * Recherche SQL en MySQL LIKE `%token%` (case-insensitive grâce à la
 * collation utf8mb4_unicode_ci par défaut). Sur les volumes Floty
 * (~5k véhicules max, ~500 entreprises, ~10k contrats), sub-50ms même
 * sans index FULLTEXT. Migration FULLTEXT possible plus tard si besoin.
 *
 * Sécurité · les tokens sont passés en bindings paramétrés (jamais
 * d'interpolation). Les wildcards `%` et `_` sont échappés via
 * {@see escapeLikeWildcards}.
 */
final class GlobalSearchService
{
    /** Limite max d'items par groupe retourné au frontend. */
    private const LIMIT_PER_GROUP = 5;

    /** Regex de détection d'année dans la query (2000-2099). */
    private const YEAR_REGEX = '/\b(20\d{2})\b/';

    public function searchAll(string $query): GlobalSearchResultData
    {
        $tokens = $this->tokenize($query);

        if (count($tokens) === 0) {
            return GlobalSearchResultData::emptyResult($query);
        }

        [$year, $nonYearTokens] = $this->extractYear($tokens);

        // 3 recherches primaires (toujours actives)
        $vehicles = $this->searchVehicles($tokens);
        $companies = $this->searchCompanies($tokens);
        $drivers = $this->searchDrivers($tokens);

        // Contrats · raccourcis vers /app/contracts filtré (≥ 2 tokens)
        $contractShortcuts = [];

        if (count($tokens) >= 2) {
            $contractShortcuts = $this->searchContractShortcuts($tokens);
        }

        // Déclarations · 1 résultat par couple (company, year) (année
        // détectée + ≥ 1 token entreprise après retrait année)
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
     * Split la query par espaces, lowercase, filter empty.
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
     * Extrait la première année 20XX trouvée dans les tokens (regex
     * `\b(20\d{2})\b`) et retourne les tokens restants. Si aucune
     * année trouvée, retourne `[null, $tokens]`.
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
     * Échappe les caractères wildcards SQL LIKE (`%`, `_`) ainsi que
     * l'antislash dans un token utilisateur. À combiner avec `%` autour
     * pour un match « contains » safe.
     */
    private function escapeLikeWildcards(string $token): string
    {
        return addcslashes($token, '%_\\');
    }

    /**
     * Applique un `WHERE CONCAT_WS(' ', ...cols) LIKE ?` pour chaque
     * token, en AND. Compatible MySQL/MariaDB · collation par défaut
     * utf8mb4_unicode_ci → case-insensitive.
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
                // Entreprises actives uniquement (left_at IS NULL) pour
                // alimenter le sublabel · 1 query supplémentaire en eager
                // load (pas N+1).
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
     * Cherche les couples (véhicule, entreprise) qui ont au moins un
     * contrat ET dont la combinaison matche tous les tokens (chaque
     * token doit matcher soit côté véhicule, soit côté entreprise).
     *
     * 1 raccourci par couple · href vers `/app/contracts?vehicleId=X&companyId=Y`.
     *
     * Implémenté en `DB::table()` (query agrégat avec aliases SQL
     * non-portés par le modèle Eloquent Contract) pour éviter les faux
     * positifs PHPStan sur les `property.notFound`. Pas de N+1 · 1
     * seule query avec GROUP BY et MAX() pour récupérer les libellés.
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
                DB::raw('COUNT(*) as contract_count'),
                DB::raw('MAX(vehicles.brand) as vehicle_brand'),
                DB::raw('MAX(vehicles.model) as vehicle_model'),
                DB::raw('MAX(vehicles.license_plate) as vehicle_plate'),
                DB::raw('MAX(companies.legal_name) as company_name'),
            ])
            ->groupBy('contracts.vehicle_id', 'contracts.company_id');

        $vehicleConcat = "CONCAT_WS(' ', vehicles.brand, vehicles.model, vehicles.license_plate)";
        $companyConcat = "CONCAT_WS(' ', companies.legal_name, companies.siren)";

        foreach ($tokens as $token) {
            $escaped = $this->escapeLikeWildcards($token);
            // Chaque token doit matcher soit côté véhicule, soit côté
            // entreprise · OR par token, AND entre tokens.
            $query->whereRaw(
                "($vehicleConcat LIKE ? OR $companyConcat LIKE ?)",
                ['%'.$escaped.'%', '%'.$escaped.'%'],
            );
        }

        $rows = $query
            ->orderByDesc('contract_count')
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

            $items[] = new GlobalSearchContractShortcutData(
                vehicleId: (int) $row->vehicle_id,
                companyId: (int) $row->company_id,
                label: sprintf('%s · chez %s', $vehicleLabel, (string) $row->company_name),
                sublabel: sprintf('%d contrat%s', $count, $count > 1 ? 's' : ''),
                count: $count,
                href: route('user.contracts.index', [
                    'vehicleId' => $row->vehicle_id,
                    'companyId' => $row->company_id,
                ]),
            );
        }

        return $items;
    }

    /**
     * Cherche les déclarations actives (`is_obsolete = false`) du
     * couple (company matchant les tokens, year donnée). 1 résultat
     * par couple (company, year).
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
     * Formate le statut d'une déclaration en label humain pour le
     * sublabel de la palette de recherche.
     */
    private function formatDeclarationStatus(FiscalDeclaration $declaration): string
    {
        return match ($declaration->status) {
            FiscalDeclarationStatus::Generated => $declaration->generated_at !== null
                ? sprintf('Générée le %s', $declaration->generated_at->format('d/m/Y'))
                : 'Générée',
            FiscalDeclarationStatus::Deferred => 'Mise de côté',
            FiscalDeclarationStatus::Draft => 'Brouillon',
        };
    }

    /**
     * Formate un SIREN brut (9 chiffres) en groupes de 3 pour
     * lisibilité (« 123 456 789 »).
     */
    private function formatSiren(string $siren): string
    {
        if (strlen($siren) !== 9) {
            return $siren;
        }

        return substr($siren, 0, 3).' '.substr($siren, 3, 3).' '.substr($siren, 6, 3);
    }
}
