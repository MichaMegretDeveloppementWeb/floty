<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\FiscalDeclaration\GenerateDeclarationAction;
use App\Enums\Company\CompanyColor;
use App\Enums\Contract\ContractType;
use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\BillingSettings;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ControlDefinition;
use App\Models\Driver;
use App\Models\FiscalDeclaration;
use App\Models\RentalDiscount;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use App\Models\VehicleYearlyPricing;
use App\Support\VehicleEvent\EventNatureCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo dataset for fiscal year 2024.
 * Produces 5 companies, 10 vehicles spanning the full fiscal matrix
 * (electric exempt, Diesel Euro 6 "most polluting", WLTP/NEDC/PA, handicap, etc.)
 * and ~200 contracts covering both below and above the 30-day LCD threshold.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Hard guard: this seeder purges and replaces business data (events,
        // contracts, vehicles...). Running it against production would
        // destroy real client data, so it fails loudly there · use the
        // targeted seeders (natures, détails, fiscal rules) instead.
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DemoSeeder est interdit en production : il purge et remplace les données métier. '
                .'Lancer uniquement les seeders ciblés (VehicleEventNatureSeeder, VehicleEventDetailSuggestionSeeder, FiscalRulesSeeder).',
            );
        }

        // No global DB::transaction: seedContracts tolerates per-row overlap
        // failures via try-catch; a transaction would silently roll back the
        // whole insert batch.
        $this->seedBillingSettings();
        $companies = $this->seedCompanies();
        $vehicles = $this->seedVehicles();
        $this->seedPricings($vehicles);
        $this->seedContracts($vehicles, $companies);
        $this->seedUnavailabilities($vehicles);
        $this->seedControls();
        $this->seedDrivers($companies);
        $this->seedContractDrivers();
        $this->seedFiscalDeclarations($companies);
        $this->seedRentalDiscounts($companies, $vehicles);
    }

    /**
     * Seed 4 demo rental discounts covering past, active, targeted and upcoming periods.
     *
     * @param  array<string, Company>  $companies
     * @param  array<int, Vehicle>  $vehicles
     */
    private function seedRentalDiscounts(array $companies, array $vehicles): void
    {
        RentalDiscount::query()->forceDelete();

        if ($companies === [] || $vehicles === []) {
            return;
        }

        $companyList = array_values($companies);
        $vehicleList = array_values($vehicles);
        $today = Carbon::today();

        // 1. Past, finished period (visible on already-issued invoices).
        RentalDiscount::factory()
            ->forCompany($companyList[0])
            ->withPeriod(Carbon::create(2024, 1, 1), Carbon::create(2024, 3, 31))
            ->withDiscountPercent(10.0)
            ->create([
                'label' => 'Remise lancement Q1 2024',
                'notes' => 'Démo · réduction "tous véhicules" sur trimestre passé.',
            ]);

        // 2. Active period, subset of vehicles.
        if (count($vehicleList) >= 2) {
            RentalDiscount::factory()
                ->forCompany($companyList[0])
                ->withPeriod($today->copy()->subMonths(3), $today->copy()->addMonths(9))
                ->withDiscountPercent(15.0)
                ->appliesToVehicles([$vehicleList[0], $vehicleList[1]])
                ->create([
                    'label' => 'Pack fidélité véhicules pilotes',
                    'notes' => 'Démo · réduction sur véhicules ciblés, période active.',
                ]);
        }

        // 3. Active period, all vehicles, second company.
        if (count($companyList) >= 2) {
            RentalDiscount::factory()
                ->forCompany($companyList[1])
                ->withPeriod($today->copy()->subMonths(1), $today->copy()->addMonths(11))
                ->withDiscountPercent(7.5)
                ->create([
                    'label' => 'Engagement annuel 2026',
                    'notes' => null,
                ]);
        }

        // 4. Upcoming period.
        RentalDiscount::factory()
            ->forCompany($companyList[0])
            ->withPeriod($today->copy()->addMonths(10), $today->copy()->addMonths(12))
            ->withDiscountPercent(5.0)
            ->create([
                'label' => 'Pré-engagement trimestre suivant',
                'notes' => 'Démo · réduction planifiée à venir.',
            ]);
    }

    /**
     * Seed historical fiscal declarations spanning the full lifecycle
     * (Draft → Generated → Superseded). Generated rows go through the real
     * GenerateDeclarationAction (real PDF + SHA-256 + sequential reference);
     * generation failures fall back to Deferred with a console warning.
     */
    private function seedFiscalDeclarations(array $companies): void
    {
        FiscalDeclaration::query()->forceDelete();

        $generateAction = app(GenerateDeclarationAction::class);

        $create = function (
            Company $company,
            int $year,
            FiscalDeclarationStatus $status,
            bool $obsolete = false,
            ?Carbon $obsoleteAt = null,
            ?array $obsoleteReasons = null,
        ) use ($generateAction): ?FiscalDeclaration {
            $draft = FiscalDeclaration::create([
                'company_id' => $company->id,
                'fiscal_year' => $year,
                'status' => FiscalDeclarationStatus::Draft,
            ]);

            $result = $draft;

            if ($status === FiscalDeclarationStatus::Generated) {
                try {
                    $generated = $generateAction->execute($draft->id);
                    $generated->forceFill([
                        'generated_at' => Carbon::create($year + 1, 1, 15, 9, 0, 0),
                    ])->save();
                    $result = $generated->fresh();
                } catch (\Throwable $e) {
                    $draft->update(['status' => FiscalDeclarationStatus::Deferred]);
                    if ($this->command !== null) {
                        $this->command->warn(sprintf(
                            '· %s %d · génération PDF impossible (fallback Deferred) · %s',
                            $company->short_code,
                            $year,
                            $e->getMessage(),
                        ));
                    }
                    $result = $draft->fresh();
                }
            } elseif ($status === FiscalDeclarationStatus::Deferred) {
                $draft->update(['status' => FiscalDeclarationStatus::Deferred]);
                $result = $draft->fresh();
            }

            if ($obsolete && $result !== null) {
                $result->forceFill([
                    'is_obsolete' => true,
                    'obsolete_at' => $obsoleteAt ?? Carbon::create($year + 1, 3, 1, 10, 0, 0),
                    'obsolete_reasons' => $obsoleteReasons,
                ])->save();
                $result = $result->fresh();
            }

            return $result;
        };

        // 2024: 8 Generated + 1 Deferred.
        $generated2024Codes = ['ACM', 'BTP', 'COR', 'DRS', 'ECO', 'HEX', 'IDF', 'LOG'];
        foreach ($generated2024Codes as $code) {
            if (! isset($companies[$code])) {
                continue;
            }
            $create($companies[$code], 2024, FiscalDeclarationStatus::Generated);
        }
        if (isset($companies['BAT'])) {
            $create($companies['BAT'], 2024, FiscalDeclarationStatus::Deferred);
        }

        // Superseded chain: ACM 2024 regenerated.
        // The old row must be marked obsolete before the new one is created:
        // decl_active_uniqueness enforces (company, year) UNIQUE while is_obsolete=false.
        if (isset($companies['ACM'])) {
            $oldAcm2024 = FiscalDeclaration::where('company_id', $companies['ACM']->id)
                ->where('fiscal_year', 2024)
                ->first();
            if ($oldAcm2024 !== null) {
                $oldAcm2024->forceFill([
                    'is_obsolete' => true,
                    'obsolete_at' => Carbon::create(2025, 4, 10, 14, 0, 0),
                    'obsolete_reasons' => [
                        'reason' => 'Régénération suite à correction VFC véhicule EE-005-EE',
                    ],
                ])->save();
                $newAcm2024 = $create($companies['ACM'], 2024, FiscalDeclarationStatus::Generated);
                if ($newAcm2024 !== null) {
                    $oldAcm2024->update(['superseded_by_id' => $newAcm2024->id]);
                }
            }
        }

        // 2025: 3 Generated + 2 Deferred + 3 Draft.
        $generated2025Codes = ['ACM', 'BTP', 'IDF'];
        foreach ($generated2025Codes as $code) {
            if (! isset($companies[$code])) {
                continue;
            }
            $create($companies[$code], 2025, FiscalDeclarationStatus::Generated);
        }
        $deferred2025Codes = ['COR', 'TUR'];
        foreach ($deferred2025Codes as $code) {
            if (! isset($companies[$code])) {
                continue;
            }
            $create($companies[$code], 2025, FiscalDeclarationStatus::Deferred);
        }
        $draft2025Codes = ['HEX', 'ECO', 'NOV'];
        foreach ($draft2025Codes as $code) {
            if (! isset($companies[$code])) {
                continue;
            }
            $create($companies[$code], 2025, FiscalDeclarationStatus::Draft);
        }

        // 2026: 2 Draft (current-year prep).
        $draft2026Codes = ['DRS', 'EOL'];
        foreach ($draft2026Codes as $code) {
            if (! isset($companies[$code])) {
                continue;
            }
            $create($companies[$code], 2026, FiscalDeclarationStatus::Draft);
        }
    }

    /**
     * Assign 1-3 drivers to each contract using a weighted distribution while
     * enforcing the per-driver non-overlap invariant.
     */
    private function seedContractDrivers(): void
    {
        DB::table('contract_drivers')->delete();

        $driverCompanies = DB::table('driver_company')->get();
        $driversByCompany = [];
        foreach ($driverCompanies as $dc) {
            $driversByCompany[$dc->company_id][] = [
                'driver_id' => $dc->driver_id,
                'joined_at' => $dc->joined_at,
                'left_at' => $dc->left_at,
            ];
        }

        $driverBusyRanges = []; // [driver_id => [[start, end], ...]]

        $weights = [1 => 60, 2 => 30, 3 => 10]; // 60% / 30% / 10%
        $cumulative = [];
        $sum = 0;
        foreach ($weights as $k => $w) {
            $sum += $w;
            $cumulative[$k] = $sum;
        }

        mt_srand(42);
        $contracts = Contract::orderBy('start_date')->get();
        foreach ($contracts as $contract) {
            $candidates = $driversByCompany[$contract->company_id] ?? [];
            $eligible = [];
            foreach ($candidates as $cand) {
                $joined = Carbon::parse($cand['joined_at']);
                $left = $cand['left_at'] !== null ? Carbon::parse($cand['left_at']) : null;

                if ($joined->gt($contract->start_date)) {
                    continue;
                }
                if ($left !== null && $left->lt($contract->end_date)) {
                    continue;
                }

                $busy = $driverBusyRanges[$cand['driver_id']] ?? [];
                $hasOverlap = false;
                foreach ($busy as [$bs, $be]) {
                    if ($contract->start_date->lte($be) && $contract->end_date->gte($bs)) {
                        $hasOverlap = true;
                        break;
                    }
                }
                if ($hasOverlap) {
                    continue;
                }

                $eligible[] = $cand['driver_id'];
            }

            if ($eligible === []) {
                continue;
            }

            $r = mt_rand(1, $sum);
            $target = 1;
            foreach ($cumulative as $k => $threshold) {
                if ($r <= $threshold) {
                    $target = $k;
                    break;
                }
            }
            $count = min($target, count($eligible));

            shuffle($eligible);
            $selected = array_slice($eligible, 0, $count);

            $now = now();
            foreach ($selected as $driverId) {
                DB::table('contract_drivers')->insert([
                    'contract_id' => $contract->id,
                    'driver_id' => $driverId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $driverBusyRanges[$driverId][] = [$contract->start_date, $contract->end_date];
            }
        }
    }

    /**
     * Default invoice issuer (singleton). Pre-fills the legal entity so the
     * first generated invoice is coherent without visiting the settings page.
     */
    private function seedBillingSettings(): void
    {
        $settings = BillingSettings::singleton();
        $settings->fill([
            'name' => 'Sogema Rent',
            'address_line_1' => 'Vessac',
            'address_line_2' => null,
            'postal_code' => '12720',
            'city' => 'Saint-André-de-Vézines',
            'siren' => null,
            'contact_email' => null,
        ]);
        $settings->save();
    }

    /**
     * Daily/weekly/monthly pricing per vehicle × year (2024 → current, +5 %/year).
     * Triplet kept consistent (W ≈ 5.7 × D, M ≈ 20 × D) so OptimalRateBreakdown
     * actually mixes months + weeks + days. Exited vehicles stop receiving rates
     * after their exit year.
     *
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedPricings(array $vehicles): void
    {
        $baseRates2024 = [
            'EA-001-AA' => [70, 400, 1400],
            'EB-002-BB' => [95, 550, 1900],
            'EC-003-CC' => [140, 800, 2800],
            'ED-004-DD' => [50, 280, 950],
            'EE-005-EE' => [45, 250, 850],
            'EF-006-FF' => [65, 370, 1300],
            'EG-007-GG' => [150, 870, 3000],
            'EH-008-HH' => [80, 460, 1600],
            'EI-009-II' => [100, 580, 2000],
            'EJ-010-JJ' => [75, 430, 1500],
            'EK-011-KK' => [60, 340, 1200],
            'EL-012-LL' => [75, 430, 1500],
        ];

        $currentYear = Carbon::now()->year;
        $years = range(2024, max($currentYear, 2026));

        foreach ($vehicles as $plate => $vehicle) {
            // Explicit rates for the first 12; generic fallback otherwise.
            [$daily2024, $weekly2024, $monthly2024] = $baseRates2024[$plate] ?? $this->estimateFallbackRates($vehicle);

            foreach ($years as $year) {
                if ($vehicle->exit_date !== null && $year > $vehicle->exit_date->year) {
                    continue;
                }

                $multiplier = 1 + 0.05 * ($year - 2024);
                $daily = (int) round($daily2024 * $multiplier);
                $weekly = (int) round($weekly2024 * $multiplier);
                $monthly = (int) round($monthly2024 * $multiplier);

                VehicleYearlyPricing::updateOrCreate(
                    ['vehicle_id' => $vehicle->id, 'year' => $year],
                    [
                        'daily_rate_cents' => $daily * 100,
                        'weekly_rate_cents' => $weekly * 100,
                        'monthly_rate_cents' => $monthly * 100,
                    ],
                );
            }
        }
    }

    /**
     * Estimation grossière des tarifs J/S/M pour un véhicule sans spec
     * explicite · sert uniquement à éviter les véhicules sans pricing
     * en démo. Triplet cohérent S ≈ 5.7×J et M ≈ 20×J.
     *
     * @return array{0:int,1:int,2:int} [daily, weekly, monthly]
     */
    private function estimateFallbackRates(Vehicle $vehicle): array
    {
        $vfc = $vehicle->fiscalCharacteristics()->whereNull('effective_to')->first();
        $kerb = $vfc?->kerb_mass ?? 1300;
        $isElectric = in_array($vfc?->energy_source?->value, ['electric', 'hydrogen', 'electric_hydrogen'], true);

        // Base par poids · 50 € à 1000 kg, +5 €/100 kg
        $daily = (int) round(50 + ($kerb - 1000) / 20);
        // Premium électrique +30 %
        if ($isElectric) {
            $daily = (int) round($daily * 1.30);
        }
        $weekly = (int) round($daily * 5.7);
        $monthly = (int) round($daily * 20);

        return [$daily, $weekly, $monthly];
    }

    /**
     * Seed 32 demo drivers covering the full Driver ↔ Company N:N matrix:
     * active, historical, multi-company, recent, orphan, and parallel-membership cases.
     *
     * @param  array<string, Company>  $companies
     */
    private function seedDrivers(array $companies): void
    {
        $specs = [
            // 15 active drivers, one per company.
            ['first' => 'Marc', 'last' => 'Dubois', 'memberships' => [['code' => 'ACM', 'joined' => '2024-01-01', 'left' => null]]],
            ['first' => 'Thomas', 'last' => 'Petit', 'memberships' => [['code' => 'BTP', 'joined' => '2024-01-10', 'left' => null]]],
            ['first' => 'Julien', 'last' => 'Garnier', 'memberships' => [['code' => 'BAT', 'joined' => '2024-02-01', 'left' => null]]],
            ['first' => 'Olivier', 'last' => 'Roche', 'memberships' => [['code' => 'COB', 'joined' => '2024-03-15', 'left' => null]]],
            ['first' => 'Camille', 'last' => 'Roux', 'memberships' => [['code' => 'DRS', 'joined' => '2024-04-01', 'left' => null]]],
            ['first' => 'Élodie', 'last' => 'Lemaire', 'memberships' => [['code' => 'EOL', 'joined' => '2024-02-20', 'left' => null]]],
            ['first' => 'Mathieu', 'last' => 'Faure', 'memberships' => [['code' => 'HEX', 'joined' => '2024-01-08', 'left' => null]]],
            ['first' => 'Sylvain', 'last' => 'Vidal', 'memberships' => [['code' => 'IDF', 'joined' => '2024-05-01', 'left' => null]]],
            ['first' => 'Nicolas', 'last' => 'Moreau', 'memberships' => [['code' => 'ECO', 'joined' => '2024-01-20', 'left' => null]]],
            ['first' => 'Clément', 'last' => 'Henry', 'memberships' => [['code' => 'LOG', 'joined' => '2024-03-04', 'left' => null]]],
            ['first' => 'Vincent', 'last' => 'Picard', 'memberships' => [['code' => 'MAG', 'joined' => '2024-02-12', 'left' => null]]],
            ['first' => 'Romain', 'last' => 'Marchand', 'memberships' => [['code' => 'NOV', 'joined' => '2024-04-15', 'left' => null]]],
            ['first' => 'Julie', 'last' => 'Bernard', 'memberships' => [['code' => 'COR', 'joined' => '2024-02-01', 'left' => null]]],
            ['first' => 'Stéphanie', 'last' => 'Robin', 'memberships' => [['code' => 'PRO', 'joined' => '2024-03-20', 'left' => null]]],
            ['first' => 'Patrick', 'last' => 'Caron', 'memberships' => [['code' => 'TUR', 'joined' => '2024-01-15', 'left' => null]]],

            // 5 historical (membership closed with left_at).
            ['first' => 'Pierre', 'last' => 'Lefebvre', 'memberships' => [['code' => 'ACM', 'joined' => '2024-01-15', 'left' => '2025-08-31']]],
            ['first' => 'Laetitia', 'last' => 'Blanc', 'memberships' => [['code' => 'BTP', 'joined' => '2024-02-01', 'left' => '2025-12-31']]],
            ['first' => 'Sébastien', 'last' => 'Fontaine', 'memberships' => [['code' => 'ECO', 'joined' => '2024-03-01', 'left' => '2024-12-31']]],
            ['first' => 'Carine', 'last' => 'Leclerc', 'memberships' => [['code' => 'COR', 'joined' => '2024-04-01', 'left' => '2026-02-28']]],
            ['first' => 'Frédéric', 'last' => 'Lambert', 'memberships' => [['code' => 'DRS', 'joined' => '2024-06-15', 'left' => '2025-09-30']]],

            // 3 multi-company (successive or parallel memberships).
            ['first' => 'Sophie', 'last' => 'Martin', 'memberships' => [
                ['code' => 'ACM', 'joined' => '2024-03-15', 'left' => '2025-06-30'],
                ['code' => 'BTP', 'joined' => '2025-07-01', 'left' => null],
            ]],
            ['first' => 'Aurélie', 'last' => 'Simon', 'memberships' => [
                ['code' => 'ECO', 'joined' => '2024-06-01', 'left' => null],
                ['code' => 'DRS', 'joined' => '2025-03-01', 'left' => null],
            ]],
            ['first' => 'David', 'last' => 'Girard', 'memberships' => [
                ['code' => 'LOG', 'joined' => '2024-01-01', 'left' => '2024-12-31'],
                ['code' => 'MAG', 'joined' => '2025-01-01', 'left' => '2025-12-31'],
                ['code' => 'NOV', 'joined' => '2026-01-01', 'left' => null],
            ]],

            // 5 recent (joined 2025-2026).
            ['first' => 'Manon', 'last' => 'Renaud', 'memberships' => [['code' => 'IDF', 'joined' => '2025-09-01', 'left' => null]]],
            ['first' => 'Hugo', 'last' => 'Charpentier', 'memberships' => [['code' => 'HEX', 'joined' => '2025-11-15', 'left' => null]]],
            ['first' => 'Sarah', 'last' => 'Joly', 'memberships' => [['code' => 'EOL', 'joined' => '2026-01-15', 'left' => null]]],
            ['first' => 'Lucas', 'last' => 'Carpentier', 'memberships' => [['code' => 'PRO', 'joined' => '2026-02-01', 'left' => null]]],
            ['first' => 'Inès', 'last' => 'Schmitt', 'memberships' => [['code' => 'NOV', 'joined' => '2026-03-15', 'left' => null]]],

            // 2 orphans (no active membership).
            ['first' => 'Bernard', 'last' => 'Delacroix', 'memberships' => [['code' => 'BAT', 'joined' => '2024-01-01', 'left' => '2024-06-30']]],
            ['first' => 'Yvonne', 'last' => 'Lefèvre', 'memberships' => [['code' => 'COB', 'joined' => '2024-05-01', 'left' => '2024-11-30']]],

            // 2 parallel memberships (two companies at the same time).
            ['first' => 'Christophe', 'last' => 'Dumas', 'memberships' => [
                ['code' => 'ACM', 'joined' => '2024-01-01', 'left' => null],
                ['code' => 'HEX', 'joined' => '2025-01-01', 'left' => null],
            ]],
            ['first' => 'Valérie', 'last' => 'Guillot', 'memberships' => [
                ['code' => 'COR', 'joined' => '2024-04-01', 'left' => null],
                ['code' => 'TUR', 'joined' => '2024-04-01', 'left' => null],
            ]],
        ];

        foreach ($specs as $spec) {
            $existing = Driver::query()
                ->where('first_name', $spec['first'])
                ->where('last_name', $spec['last'])
                ->first();
            if ($existing !== null) {
                continue;
            }

            $driver = Driver::create([
                'first_name' => $spec['first'],
                'last_name' => $spec['last'],
            ]);

            foreach ($spec['memberships'] as $m) {
                $company = $companies[$m['code']] ?? null;
                if ($company === null) {
                    continue;
                }
                DB::table('driver_company')->insert([
                    'driver_id' => $driver->id,
                    'company_id' => $company->id,
                    'joined_at' => $m['joined'],
                    'left_at' => $m['left'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Seed 15 sectoral companies plus one inactive ("ZZZ") edge case.
     * IDF Consulting is in Paris on purpose so the IDF carte grise surcharge can be exercised.
     *
     * @return array<string, Company>
     */
    private function seedCompanies(): array
    {
        $specs = [
            // BTP / Construction (4)
            ['code' => 'ACM', 'name' => 'ACME Logistique', 'siren' => '812345678', 'color' => CompanyColor::Indigo, 'city' => 'Lyon'],
            ['code' => 'BTP', 'name' => 'BTP Confort', 'siren' => '813456789', 'color' => CompanyColor::Amber, 'city' => 'Grenoble'],
            ['code' => 'BAT', 'name' => 'Bâti Express', 'siren' => '817890123', 'color' => CompanyColor::Teal, 'city' => 'Saint-Étienne'],
            ['code' => 'COB', 'name' => 'Constructions Bossard', 'siren' => '818901234', 'color' => CompanyColor::Orange, 'city' => 'Marseille'],
            // Services / Conseil (4)
            ['code' => 'DRS', 'name' => 'Dauphiné Services', 'siren' => '815678901', 'color' => CompanyColor::Rose, 'city' => 'Valence'],
            ['code' => 'EOL', 'name' => 'Eole Conseils', 'siren' => '819012345', 'color' => CompanyColor::Cyan, 'city' => 'Toulouse'],
            ['code' => 'HEX', 'name' => 'Hexagone Solutions', 'siren' => '820123456', 'color' => CompanyColor::Indigo, 'city' => 'Bordeaux'],
            ['code' => 'IDF', 'name' => 'IDF Consulting', 'siren' => '821234567', 'color' => CompanyColor::Amber, 'city' => 'Paris'],
            // Distribution / Logistique (4)
            ['code' => 'ECO', 'name' => 'Éco Distribution', 'siren' => '816789012', 'color' => CompanyColor::Violet, 'city' => 'Lille'],
            ['code' => 'LOG', 'name' => 'LogiPro Transport', 'siren' => '822345678', 'color' => CompanyColor::Emerald, 'city' => 'Nantes'],
            ['code' => 'MAG', 'name' => 'MagaLogistic', 'siren' => '823456789', 'color' => CompanyColor::Rose, 'city' => 'Strasbourg'],
            ['code' => 'NOV', 'name' => 'Novexpress', 'siren' => '824567890', 'color' => CompanyColor::Violet, 'city' => 'Nice'],
            // Événementiel / Tourisme (3)
            ['code' => 'COR', 'name' => 'Corsica Events', 'siren' => '814567890', 'color' => CompanyColor::Emerald, 'city' => 'Ajaccio'],
            ['code' => 'PRO', 'name' => 'ProSpektacle', 'siren' => '825678901', 'color' => CompanyColor::Teal, 'city' => 'Reims'],
            ['code' => 'TUR', 'name' => 'Tourisme Atlantique', 'siren' => '826789012', 'color' => CompanyColor::Orange, 'city' => 'La Rochelle'],
            // Inactive 16th company (legacy cessation).
            ['code' => 'ZZZ', 'name' => 'Ex-Logistique SARL (cessée)', 'siren' => '827890123', 'color' => CompanyColor::Cyan, 'city' => 'Lyon', 'is_active' => false],
        ];

        $created = [];
        foreach ($specs as $spec) {
            $created[$spec['code']] = Company::updateOrCreate(
                ['short_code' => $spec['code']],
                [
                    'legal_name' => $spec['name'],
                    'siren' => $spec['siren'],
                    'city' => $spec['city'],
                    'postal_code' => $this->postalFor($spec['city']),
                    'country' => 'FR',
                    'color' => $spec['color'],
                    'is_active' => $spec['is_active'] ?? true,
                ],
            );
        }

        return $created;
    }

    private function postalFor(string $city): string
    {
        return match ($city) {
            'Lyon' => '69003',
            'Grenoble' => '38000',
            'Saint-Étienne' => '42000',
            'Marseille' => '13001',
            'Valence' => '26000',
            'Toulouse' => '31000',
            'Bordeaux' => '33000',
            'Paris' => '75008',
            'Lille' => '59000',
            'Nantes' => '44000',
            'Strasbourg' => '67000',
            'Nice' => '06000',
            'Ajaccio' => '20000',
            'Reims' => '51100',
            'La Rochelle' => '17000',
            default => '75001',
        };
    }

    /**
     * @return array<string, Vehicle>
     */
    private function seedVehicles(): array
    {
        $specs = [
            // Peugeot 308 essence Euro 6 WLTP 100 g/km - exemple officiel BOFiP
            [
                'plate' => 'EA-001-AA', 'brand' => 'Peugeot', 'model' => '308',
                'regFrench' => '2022-06-15', 'regOrigin' => '2022-06-15', 'econ' => '2022-06-15',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6,
                'pollutant' => PollutantCategory::Category1,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 100, 'pa' => 6, 'kerb' => 1340,
            ],
            // Renault Trafic Diesel Euro 6 - « plus polluants »
            [
                'plate' => 'EB-002-BB', 'brand' => 'Renault', 'model' => 'Trafic',
                'regFrench' => '2021-04-10', 'regOrigin' => '2021-04-10', 'econ' => '2021-04-10',
                'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 6,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 165, 'pa' => 8, 'kerb' => 1950,
                'n1PassengerTransport' => true,
            ],
            // Tesla Model 3 électrique - catégorie E, exonération CO₂
            [
                'plate' => 'EC-003-CC', 'brand' => 'Tesla', 'model' => 'Model 3',
                'regFrench' => '2023-02-14', 'regOrigin' => '2023-02-14', 'econ' => '2023-02-14',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Electric, 'euro' => null,
                'pollutant' => PollutantCategory::E,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 9, 'kerb' => 1844,
            ],
            // Peugeot 207 essence NEDC 130 g/km - vieille immat. 2010
            [
                'plate' => 'ED-004-DD', 'brand' => 'Peugeot', 'model' => '207',
                'regFrench' => '2010-06-15', 'regOrigin' => '2010-06-15', 'econ' => '2010-06-15',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro4,
                'pollutant' => PollutantCategory::MostPolluting, // essence pré-Euro 5 → most_polluting
                'method' => HomologationMethod::Nedc, 'co2Nedc' => 130, 'pa' => 5, 'kerb' => 1150,
            ],
            // Renault 21 gasoline 7 PA (too old for NEDC). Multi-VFC case stressing
            // the PA branch of the fiscal segmentation engine.
            [
                'plate' => 'EE-005-EE', 'brand' => 'Renault', 'model' => '21 Nevada',
                'regFrench' => '2002-05-15', 'regOrigin' => '2002-05-15', 'econ' => '2002-05-15',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::StationWagon, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro3,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Pa, 'pa' => 7, 'kerb' => 1200,
                'extraVfcs' => [
                    ['from' => '2020-01-01', 'pa' => 8],
                    ['from' => '2024-03-15', 'pa' => 7],
                    ['from' => '2024-09-10', 'pa' => 8],
                ],
            ],
            // Toyota Yaris hybride essence Euro 6 WLTP 95 g/km
            [
                'plate' => 'EF-006-FF', 'brand' => 'Toyota', 'model' => 'Yaris Hybrid',
                'regFrench' => '2023-09-01', 'regOrigin' => '2023-09-01', 'econ' => '2023-09-01',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::NonPluginHybrid, 'underlying' => UnderlyingCombustionEngineType::Gasoline,
                'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::Category1, // hybride essence → cat 1
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 95, 'pa' => 5, 'kerb' => 1180,
            ],
            // BMW Série 5 Diesel Euro 6 - « plus polluants » gros CO₂
            [
                'plate' => 'EG-007-GG', 'brand' => 'BMW', 'model' => 'Série 5 520d',
                'regFrench' => '2022-11-20', 'regOrigin' => '2022-11-20', 'econ' => '2022-11-20',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 155, 'pa' => 9, 'kerb' => 1700,
            ],
            // Peugeot Partner Diesel Euro 6 N1 passenger transport.
            // Multi-VFC same-year case: in-year CO2 re-homologation transitions.
            [
                'plate' => 'EH-008-HH', 'brand' => 'Peugeot', 'model' => 'Partner 2 rangs',
                'regFrench' => '2023-03-05', 'regOrigin' => '2023-03-05', 'econ' => '2023-03-05',
                'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 145, 'pa' => 7, 'kerb' => 1500,
                'n1PassengerTransport' => true,
                'extraVfcs' => [
                    ['from' => '2024-04-01', 'co2Wltp' => 130],
                    ['from' => '2024-09-01', 'co2Wltp' => 150],
                ],
            ],
            // Ford Transit Custom Diesel Euro 6 - utilitaire de transport
            [
                'plate' => 'EI-009-II', 'brand' => 'Ford', 'model' => 'Transit Custom',
                'regFrench' => '2020-08-10', 'regOrigin' => '2020-08-10', 'econ' => '2020-08-10',
                'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 175, 'pa' => 9, 'kerb' => 2000,
                'n1PassengerTransport' => true,
            ],
            // Renault Kangoo handicap - exonération totale
            [
                'plate' => 'EJ-010-JJ', 'brand' => 'Renault', 'model' => 'Kangoo TPMR',
                'regFrench' => '2022-04-12', 'regOrigin' => '2022-04-12', 'econ' => '2022-04-12',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::Handicap, 'cat' => ReceptionCategory::M1, 'seats' => 4,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 130, 'pa' => 6, 'kerb' => 1450,
                'handicapAccess' => true,
            ],
            // Citroën C3 sold mid-2025. Exercises the visibility matrix (ADR-0018):
            // visible in the 2025 heatmap with post-30/04 cells greyed, hidden from 2026+.
            [
                'plate' => 'EK-011-KK', 'brand' => 'Citroën', 'model' => 'C3',
                'regFrench' => '2019-09-01', 'regOrigin' => '2019-09-01', 'econ' => '2019-09-01',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6,
                'pollutant' => PollutantCategory::Category1,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 115, 'pa' => 5, 'kerb' => 1100,
                'exitDate' => '2025-04-30',
                'exitReason' => VehicleExitReason::Sold,
                'currentStatus' => VehicleStatus::Sold,
            ],
            // Renault Mégane gasoline Euro 6: exercises the per-VFC segmented engine
            // (initial 102 g/km, corrected to 145 g/km on 2024-06-16).
            [
                'plate' => 'EL-012-LL', 'brand' => 'Renault', 'model' => 'Mégane E-Tech',
                'regFrench' => '2023-08-20', 'regOrigin' => '2023-08-20', 'econ' => '2023-08-20',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::Category1,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 102, 'pa' => 6, 'kerb' => 1380,
                'extraVfcs' => [
                    ['from' => '2024-06-16', 'co2Wltp' => 145],
                ],
            ],

            // G1: WLTP Cat1 gasoline (10 vehicles, emissions 50-150 g).
            ['plate' => 'EM-013-MM', 'brand' => 'Citroën', 'model' => 'C3', 'regFrench' => '2023-04-12', 'regOrigin' => '2023-04-12', 'econ' => '2023-04-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 99, 'pa' => 5, 'kerb' => 1085],
            ['plate' => 'EN-014-NN', 'brand' => 'Fiat', 'model' => '500', 'regFrench' => '2022-07-10', 'regOrigin' => '2022-07-10', 'econ' => '2022-07-10', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 90, 'pa' => 4, 'kerb' => 950],
            ['plate' => 'EO-015-OO', 'brand' => 'Volkswagen', 'model' => 'Polo', 'regFrench' => '2022-10-18', 'regOrigin' => '2022-10-18', 'econ' => '2022-10-18', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 105, 'pa' => 5, 'kerb' => 1180],
            ['plate' => 'EP-016-PP', 'brand' => 'Mini', 'model' => 'Cooper', 'regFrench' => '2023-01-25', 'regOrigin' => '2023-01-25', 'econ' => '2023-01-25', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 120, 'pa' => 6, 'kerb' => 1230],
            ['plate' => 'EQ-017-QQ', 'brand' => 'Audi', 'model' => 'A1 Sportback', 'regFrench' => '2023-06-08', 'regOrigin' => '2023-06-08', 'econ' => '2023-06-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 130, 'pa' => 6, 'kerb' => 1265],
            ['plate' => 'ER-018-RR', 'brand' => 'BMW', 'model' => 'Série 1 118i', 'regFrench' => '2022-12-14', 'regOrigin' => '2022-12-14', 'econ' => '2022-12-14', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 135, 'pa' => 7, 'kerb' => 1390],
            ['plate' => 'ES-019-SS', 'brand' => 'Mercedes', 'model' => 'Classe A 180', 'regFrench' => '2023-09-30', 'regOrigin' => '2023-09-30', 'econ' => '2023-09-30', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 140, 'pa' => 7, 'kerb' => 1395],
            ['plate' => 'ET-020-TT', 'brand' => 'Volvo', 'model' => 'XC40', 'regFrench' => '2023-03-21', 'regOrigin' => '2023-03-21', 'econ' => '2023-03-21', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 145, 'pa' => 7, 'kerb' => 1635],
            ['plate' => 'EU-021-UU', 'brand' => 'Honda', 'model' => 'Civic', 'regFrench' => '2022-08-22', 'regOrigin' => '2022-08-22', 'econ' => '2022-08-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 115, 'pa' => 6, 'kerb' => 1320],
            ['plate' => 'EV-022-VV', 'brand' => 'Renault', 'model' => 'Captur', 'regFrench' => '2023-05-17', 'regOrigin' => '2023-05-17', 'econ' => '2023-05-17', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 110, 'pa' => 5, 'kerb' => 1235],

            // G2: WLTP MostPolluting Diesel (7 vehicles).
            ['plate' => 'EW-023-WW', 'brand' => 'Ford', 'model' => 'Transit', 'regFrench' => '2021-11-05', 'regOrigin' => '2021-11-05', 'econ' => '2021-11-05', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 175, 'pa' => 9, 'kerb' => 2100, 'n1PassengerTransport' => true],
            ['plate' => 'EX-024-XX', 'brand' => 'Mercedes', 'model' => 'Vito', 'regFrench' => '2022-02-18', 'regOrigin' => '2022-02-18', 'econ' => '2022-02-18', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 170, 'pa' => 9, 'kerb' => 2050, 'n1PassengerTransport' => true],
            ['plate' => 'EY-025-YY', 'brand' => 'Peugeot', 'model' => '5008 BlueHDi', 'regFrench' => '2022-09-12', 'regOrigin' => '2022-09-12', 'econ' => '2022-09-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 7, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 150, 'pa' => 8, 'kerb' => 1650],
            ['plate' => 'EZ-026-ZZ', 'brand' => 'Volkswagen', 'model' => 'Tiguan TDI', 'regFrench' => '2023-01-09', 'regOrigin' => '2023-01-09', 'econ' => '2023-01-09', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 160, 'pa' => 8, 'kerb' => 1730],
            ['plate' => 'FA-027-AA', 'brand' => 'Land Rover', 'model' => 'Defender', 'regFrench' => '2022-04-22', 'regOrigin' => '2022-04-22', 'econ' => '2022-04-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 190, 'pa' => 11, 'kerb' => 2200],
            ['plate' => 'FB-028-BB', 'brand' => 'Renault', 'model' => 'Master', 'regFrench' => '2021-06-30', 'regOrigin' => '2021-06-30', 'econ' => '2021-06-30', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 195, 'pa' => 10, 'kerb' => 2400, 'n1PassengerTransport' => true],
            ['plate' => 'FC-029-CC', 'brand' => 'Audi', 'model' => 'Q5 TDI', 'regFrench' => '2023-07-18', 'regOrigin' => '2023-07-18', 'econ' => '2023-07-18', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 165, 'pa' => 9, 'kerb' => 1850],

            // G3: WLTP Cat E (electric/H2), 4 vehicles, CO2 exempt.
            ['plate' => 'FD-030-DD', 'brand' => 'Renault', 'model' => 'Zoé', 'regFrench' => '2023-02-08', 'regOrigin' => '2023-02-08', 'econ' => '2023-02-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Electric, 'euro' => null, 'pollutant' => PollutantCategory::E, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 7, 'kerb' => 1502],
            ['plate' => 'FE-031-EE', 'brand' => 'Nissan', 'model' => 'Leaf', 'regFrench' => '2022-11-22', 'regOrigin' => '2022-11-22', 'econ' => '2022-11-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Electric, 'euro' => null, 'pollutant' => PollutantCategory::E, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 6, 'kerb' => 1580],
            ['plate' => 'FF-032-FF', 'brand' => 'Hyundai', 'model' => 'Nexo', 'regFrench' => '2023-08-15', 'regOrigin' => '2023-08-15', 'econ' => '2023-08-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Hydrogen, 'euro' => null, 'pollutant' => PollutantCategory::E, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 8, 'kerb' => 1814],
            ['plate' => 'FG-033-GG', 'brand' => 'Renault', 'model' => 'Master ZE', 'regFrench' => '2023-05-04', 'regOrigin' => '2023-05-04', 'econ' => '2023-05-04', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3, 'energy' => EnergySource::Electric, 'euro' => null, 'pollutant' => PollutantCategory::E, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 8, 'kerb' => 2380, 'n1PassengerTransport' => true],

            // G4: NEDC Cat1 legacy gasoline (4 vehicles).
            ['plate' => 'FH-034-HH', 'brand' => 'Citroën', 'model' => 'C4', 'regFrench' => '2014-03-15', 'regOrigin' => '2014-03-15', 'econ' => '2014-03-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 145, 'pa' => 6, 'kerb' => 1250],
            ['plate' => 'FI-035-II', 'brand' => 'Ford', 'model' => 'Fiesta', 'regFrench' => '2016-06-20', 'regOrigin' => '2016-06-20', 'econ' => '2016-06-20', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 125, 'pa' => 5, 'kerb' => 1135],
            ['plate' => 'FJ-036-JJ', 'brand' => 'Renault', 'model' => 'Mégane III', 'regFrench' => '2015-10-12', 'regOrigin' => '2015-10-12', 'econ' => '2015-10-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 140, 'pa' => 6, 'kerb' => 1290],
            ['plate' => 'FK-037-KK', 'brand' => 'Volkswagen', 'model' => 'Golf VII', 'regFrench' => '2014-08-08', 'regOrigin' => '2014-08-08', 'econ' => '2014-08-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 138, 'pa' => 6, 'kerb' => 1280],

            // G5: NEDC MostPolluting Diesel legacy (4 vehicles).
            ['plate' => 'FL-038-LL', 'brand' => 'Peugeot', 'model' => '308 HDi', 'regFrench' => '2013-09-05', 'regOrigin' => '2013-09-05', 'econ' => '2013-09-05', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 150, 'pa' => 7, 'kerb' => 1450],
            ['plate' => 'FM-039-MM', 'brand' => 'Citroën', 'model' => 'Berlingo HDi', 'regFrench' => '2015-04-22', 'regOrigin' => '2015-04-22', 'econ' => '2015-04-22', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 160, 'pa' => 7, 'kerb' => 1620, 'n1PassengerTransport' => true],
            ['plate' => 'FN-040-NN', 'brand' => 'Renault', 'model' => 'Kangoo dCi', 'regFrench' => '2014-11-18', 'regOrigin' => '2014-11-18', 'econ' => '2014-11-18', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 155, 'pa' => 6, 'kerb' => 1500, 'n1PassengerTransport' => true, 'n1RemovableSecondRowSeat' => true],
            ['plate' => 'FO-041-OO', 'brand' => 'Ford', 'model' => 'Focus TDCi', 'regFrench' => '2016-02-29', 'regOrigin' => '2016-02-29', 'econ' => '2016-02-29', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 145, 'pa' => 7, 'kerb' => 1380],

            // G6: PA vintage (pre-2006), 5 vehicles to stress the PA schedule.
            ['plate' => 'FP-042-PP', 'brand' => 'Peugeot', 'model' => '405', 'regFrench' => '1998-06-15', 'regOrigin' => '1998-06-15', 'econ' => '1998-06-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro2, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 6, 'kerb' => 1100],
            ['plate' => 'FQ-043-QQ', 'brand' => 'Citroën', 'model' => 'AX', 'regFrench' => '1995-03-10', 'regOrigin' => '1995-03-10', 'econ' => '1995-03-10', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro1, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 4, 'kerb' => 700],
            ['plate' => 'FR-044-RR', 'brand' => 'Renault', 'model' => '19 Chamade', 'regFrench' => '1993-09-22', 'regOrigin' => '1993-09-22', 'econ' => '1993-09-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => null, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 5, 'kerb' => 880],
            ['plate' => 'FS-045-SS', 'brand' => 'Mercedes', 'model' => '190E', 'regFrench' => '1991-08-04', 'regOrigin' => '1991-08-04', 'econ' => '1991-08-04', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => null, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 8, 'kerb' => 1180],
            ['plate' => 'FT-046-TT', 'brand' => 'BMW', 'model' => 'E30 320i', 'regFrench' => '1989-05-18', 'regOrigin' => '1989-05-18', 'econ' => '1989-05-18', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => null, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 9, 'kerb' => 1170],

            // G7: E85 edge cases (40 % WLTP abatement / 2 PA bonus), 5 vehicles.
            // E85 WLTP 130 → 78 g/km post-abatement.
            ['plate' => 'FU-047-UU', 'brand' => 'Renault', 'model' => 'Captur E85', 'regFrench' => '2023-04-12', 'regOrigin' => '2023-04-12', 'econ' => '2023-04-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 130, 'pa' => 6, 'kerb' => 1340, 'acceptsE85' => true],
            // E85 WLTP 100 → 60 g/km (BOFiP § 240).
            ['plate' => 'FV-048-VV', 'brand' => 'Ford', 'model' => 'Focus E85', 'regFrench' => '2022-09-10', 'regOrigin' => '2022-09-10', 'econ' => '2022-09-10', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 100, 'pa' => 6, 'kerb' => 1380, 'acceptsE85' => true],
            // E85 WLTP 251 g/km: above cap, abatement lost.
            ['plate' => 'FW-049-WW', 'brand' => 'Dacia', 'model' => 'Duster Bi-Fuel E85', 'regFrench' => '2023-07-08', 'regOrigin' => '2023-07-08', 'econ' => '2023-07-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 251, 'pa' => 8, 'kerb' => 1480, 'acceptsE85' => true],
            // E85 PA 12: inclusive boundary, abatement applies (→ 10 PA).
            ['plate' => 'FX-050-XX', 'brand' => 'Renault', 'model' => 'Safrane V6 E85', 'regFrench' => '1997-04-22', 'regOrigin' => '1997-04-22', 'econ' => '1997-04-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro1, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 12, 'kerb' => 1620, 'acceptsE85' => true],
            // E85 PA 13: exclusive boundary, abatement lost.
            ['plate' => 'FY-051-YY', 'brand' => 'Citroën', 'model' => 'XM Pallas E85', 'regFrench' => '1996-11-15', 'regOrigin' => '1996-11-15', 'econ' => '1996-11-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro2, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 13, 'kerb' => 1580, 'acceptsE85' => true],

            // G8: handicap_access + conditional hybrid (R-2024-017).
            // Handicap access (full zeroing).
            ['plate' => 'FZ-052-ZZ', 'brand' => 'Volkswagen', 'model' => 'Caddy TPMR', 'regFrench' => '2022-08-30', 'regOrigin' => '2022-08-30', 'econ' => '2022-08-30', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::Handicap, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 155, 'pa' => 7, 'kerb' => 1620, 'handicapAccess' => true],
            // Non-plug-in gasoline hybrid eligible to R-2024-017 (WLTP ≤ 60).
            ['plate' => 'GA-053-AA', 'brand' => 'Toyota', 'model' => 'Prius Hybrid', 'regFrench' => '2020-05-04', 'regOrigin' => '2020-05-04', 'econ' => '2020-05-04', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::NonPluginHybrid, 'underlying' => UnderlyingCombustionEngineType::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 45, 'pa' => 5, 'kerb' => 1390],

            // G9: out-of-scope M1/N1 (R-XXXX-004), 5 vehicles.
            // M1 ambulance (m1_special_use).
            ['plate' => 'GB-054-BB', 'brand' => 'Mercedes', 'model' => 'Sprinter Ambulance', 'regFrench' => '2022-12-01', 'regOrigin' => '2022-12-01', 'econ' => '2022-12-01', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 180, 'pa' => 9, 'kerb' => 2400, 'm1SpecialUse' => true],
            // M1 hearse.
            ['plate' => 'GC-055-CC', 'brand' => 'Renault', 'model' => 'Master Funéraire', 'regFrench' => '2021-04-20', 'regOrigin' => '2021-04-20', 'econ' => '2021-04-20', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 3, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 195, 'pa' => 10, 'kerb' => 2350, 'm1SpecialUse' => true],
            // N1 van without second row and no passenger transport: out of scope.
            ['plate' => 'GD-056-DD', 'brand' => 'Iveco', 'model' => 'Daily Fourgon', 'regFrench' => '2022-06-15', 'regOrigin' => '2022-06-15', 'econ' => '2022-06-15', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 200, 'pa' => 10, 'kerb' => 2700],
            // N1 pickup 5 seats for ski-lift use: out of scope.
            ['plate' => 'GE-057-EE', 'brand' => 'Toyota', 'model' => 'Hilux Pickup', 'regFrench' => '2023-01-12', 'regOrigin' => '2023-01-12', 'econ' => '2023-01-12', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::Pickup, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 220, 'pa' => 11, 'kerb' => 2100, 'n1SkiLiftUse' => true],
            // N1 station wagon (out of scope).
            ['plate' => 'GF-058-FF', 'brand' => 'Land Rover', 'model' => 'Discovery N1', 'regFrench' => '2022-03-30', 'regOrigin' => '2022-03-30', 'econ' => '2022-03-30', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::StationWagon, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 205, 'pa' => 11, 'kerb' => 2350],

            // G10: lifecycle exits (5 vehicles).
            // Sold mid-2024 (in addition to EK-011-KK above).
            ['plate' => 'GG-059-GG', 'brand' => 'Peugeot', 'model' => '208', 'regFrench' => '2019-07-22', 'regOrigin' => '2019-07-22', 'econ' => '2019-07-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 110, 'pa' => 5, 'kerb' => 1090, 'exitDate' => '2024-08-15', 'exitReason' => VehicleExitReason::Sold, 'currentStatus' => VehicleStatus::Sold],
            // Destroyed mid-2025.
            ['plate' => 'GH-060-HH', 'brand' => 'Renault', 'model' => 'Twingo', 'regFrench' => '2020-04-08', 'regOrigin' => '2020-04-08', 'econ' => '2020-04-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 100, 'pa' => 4, 'kerb' => 980, 'exitDate' => '2025-07-20', 'exitReason' => VehicleExitReason::Destroyed, 'currentStatus' => VehicleStatus::Destroyed],
            // Stolen unrecovered mid-2026.
            ['plate' => 'GI-061-II', 'brand' => 'Citroën', 'model' => 'C1', 'regFrench' => '2018-11-12', 'regOrigin' => '2018-11-12', 'econ' => '2018-11-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 95, 'pa' => 4, 'kerb' => 850, 'exitDate' => '2026-04-30', 'exitReason' => VehicleExitReason::StolenUnrecovered, 'currentStatus' => VehicleStatus::Other],
            // Sold mid-2025 (additional case).
            ['plate' => 'GJ-062-JJ', 'brand' => 'Volkswagen', 'model' => 'Up!', 'regFrench' => '2019-02-14', 'regOrigin' => '2019-02-14', 'econ' => '2019-02-14', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 105, 'pa' => 5, 'kerb' => 990, 'exitDate' => '2025-10-31', 'exitReason' => VehicleExitReason::Sold, 'currentStatus' => VehicleStatus::Sold],
            // Multi-VFC: E85 enabled mid-2025.
            ['plate' => 'GK-063-KK', 'brand' => 'Dacia', 'model' => 'Sandero', 'regFrench' => '2024-01-15', 'regOrigin' => '2024-01-15', 'econ' => '2024-01-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 120, 'pa' => 5, 'kerb' => 1150, 'extraVfcs' => [['from' => '2025-07-01', 'acceptsE85' => true]]],
            // Multi-VFC: PollutantCategory shifts Cat1 → MostPolluting via Euro downgrade.
            ['plate' => 'GL-064-LL', 'brand' => 'Suzuki', 'model' => 'Swift', 'regFrench' => '2024-02-20', 'regOrigin' => '2024-02-20', 'econ' => '2024-02-20', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 125, 'pa' => 5, 'kerb' => 1080, 'extraVfcs' => [['from' => '2025-09-01', 'pollutant' => PollutantCategory::MostPolluting, 'euro' => EuroStandard::Euro4]]],

            // Extended-maintenance edge case (no active contract).
            ['plate' => 'GM-065-MM', 'brand' => 'Skoda', 'model' => 'Octavia', 'regFrench' => '2022-05-12', 'regOrigin' => '2022-05-12', 'econ' => '2022-05-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 145, 'pa' => 7, 'kerb' => 1480, 'currentStatus' => VehicleStatus::Maintenance],
        ];

        $created = [];
        foreach ($specs as $spec) {
            $vehicle = Vehicle::updateOrCreate(
                ['license_plate' => $spec['plate']],
                [
                    'brand' => $spec['brand'],
                    'model' => $spec['model'],
                    'first_french_registration_date' => Carbon::parse($spec['regFrench']),
                    'first_origin_registration_date' => Carbon::parse($spec['regOrigin']),
                    'first_economic_use_date' => Carbon::parse($spec['econ']),
                    'acquisition_date' => Carbon::parse($spec['econ']),
                    'current_status' => $spec['currentStatus'] ?? VehicleStatus::Active,
                    'exit_date' => isset($spec['exitDate']) ? Carbon::parse($spec['exitDate']) : null,
                    'exit_reason' => $spec['exitReason'] ?? null,
                ],
            );

            // Initial VFC: single current version.
            $vehicle->fiscalCharacteristics()->delete();
            VehicleFiscalCharacteristics::create([
                'vehicle_id' => $vehicle->id,
                'effective_from' => Carbon::parse($spec['regFrench']),
                'effective_to' => null,
                'reception_category' => $spec['cat'],
                'vehicle_user_type' => $spec['user'],
                'body_type' => $spec['body'],
                'seats_count' => $spec['seats'],
                'energy_source' => $spec['energy'],
                'underlying_combustion_engine_type' => $spec['underlying'] ?? null,
                'euro_standard' => $spec['euro'] ?? null,
                'pollutant_category' => $spec['pollutant'],
                'homologation_method' => $spec['method'],
                'co2_wltp' => $spec['co2Wltp'] ?? null,
                'co2_nedc' => $spec['co2Nedc'] ?? null,
                'taxable_horsepower' => $spec['pa'],
                'kerb_mass' => $spec['kerb'] ?? null,
                'handicap_access' => $spec['handicapAccess'] ?? false,
                'accepts_e85' => $spec['acceptsE85'] ?? false,
                'n1_passenger_transport' => $spec['n1PassengerTransport'] ?? false,
                'n1_removable_second_row_seat' => $spec['n1RemovableSecondRowSeat'] ?? false,
                'm1_special_use' => $spec['m1SpecialUse'] ?? false,
                'n1_ski_lift_use' => $spec['n1SkiLiftUse'] ?? false,
                'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
            ]);

            $created[$spec['plate']] = $vehicle;
        }

        // Multi-VFC: for vehicles flagged with extraVfcs, close the previous current
        // version one day before the new effective_from and create a new VFC inheriting
        // the spec fields, then overriding the entries present in extraVfcs.
        foreach ($specs as $spec) {
            if (! isset($spec['extraVfcs']) || $spec['extraVfcs'] === []) {
                continue;
            }
            $vehicle = $created[$spec['plate']];

            // Inherit immutable spec fields; each extraVfc may override a subset.
            $inheritedFields = [
                'reception_category' => $spec['cat'],
                'vehicle_user_type' => $spec['user'],
                'body_type' => $spec['body'],
                'seats_count' => $spec['seats'],
                'energy_source' => $spec['energy'],
                'underlying_combustion_engine_type' => $spec['underlying'] ?? null,
                'euro_standard' => $spec['euro'] ?? null,
                'pollutant_category' => $spec['pollutant'],
                'homologation_method' => $spec['method'],
                'co2_wltp' => $spec['co2Wltp'] ?? null,
                'co2_nedc' => $spec['co2Nedc'] ?? null,
                'taxable_horsepower' => $spec['pa'],
                'kerb_mass' => $spec['kerb'] ?? null,
                'handicap_access' => $spec['handicapAccess'] ?? false,
                'accepts_e85' => $spec['acceptsE85'] ?? false,
                'n1_passenger_transport' => $spec['n1PassengerTransport'] ?? false,
                'n1_removable_second_row_seat' => $spec['n1RemovableSecondRowSeat'] ?? false,
                'm1_special_use' => $spec['m1SpecialUse'] ?? false,
                'n1_ski_lift_use' => $spec['n1SkiLiftUse'] ?? false,
                'change_reason' => FiscalCharacteristicsChangeReason::Recharacterization,
            ];

            foreach ($spec['extraVfcs'] as $entry) {
                $effectiveFrom = Carbon::parse($entry['from']);
                // Close the previous current VFC one day before.
                $current = $vehicle->fiscalCharacteristics()
                    ->whereNull('effective_to')
                    ->latest('effective_from')
                    ->firstOrFail();
                $current->update([
                    'effective_to' => $effectiveFrom->copy()->subDay(),
                ]);

                $fields = $inheritedFields;
                if (array_key_exists('co2Wltp', $entry)) {
                    $fields['co2_wltp'] = $entry['co2Wltp'];
                }
                if (array_key_exists('co2Nedc', $entry)) {
                    $fields['co2_nedc'] = $entry['co2Nedc'];
                }
                if (array_key_exists('pa', $entry)) {
                    $fields['taxable_horsepower'] = $entry['pa'];
                }
                if (array_key_exists('energy', $entry)) {
                    $fields['energy_source'] = $entry['energy'];
                }
                if (array_key_exists('euro', $entry)) {
                    $fields['euro_standard'] = $entry['euro'];
                }
                if (array_key_exists('pollutant', $entry)) {
                    $fields['pollutant_category'] = $entry['pollutant'];
                }
                if (array_key_exists('method', $entry)) {
                    $fields['homologation_method'] = $entry['method'];
                }
                if (array_key_exists('acceptsE85', $entry)) {
                    $fields['accepts_e85'] = $entry['acceptsE85'];
                }

                VehicleFiscalCharacteristics::create([
                    'vehicle_id' => $vehicle->id,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                    ...$fields,
                ]);
            }
        }

        return $created;
    }

    /**
     * Contrats démo sur les 3 années (2024, 2025, 2026) couvrant tous les
     * cas particuliers fiscaux · LLD/LCD, cross-année, scissions ADR-0022,
     * multi-VFC, clusters de risque pré-déclaration. Une entrée du plan =
     * un contrat. Le `contract_type` est déduit de la durée (≤ 30 j →
     * `lcd`, sinon `lld`).
     *
     * @param  array<string, Vehicle>  $vehicles
     * @param  array<string, Company>  $companies
     */
    private function seedContracts(array $vehicles, array $companies): void
    {
        // Nettoyage global · on repart à blanc pour les 3 années.
        Contract::query()->forceDelete();

        $plans = array_merge(
            $this->buildContractPlan2024(),
            $this->buildContractPlan2025(),
            $this->buildContractPlan2026(),
        );

        // Σ'.6 · compteur séquentiel par entreprise pour générer des
        // contract_reference auto-incrémentés (`CTR-{year}-{code}-{seq}`).
        $refSeqByCompany = [];

        foreach ($plans as $row) {
            $vehicle = $vehicles[$row['plate']] ?? null;
            $company = $companies[$row['company']] ?? null;
            if ($vehicle === null || $company === null) {
                continue;
            }

            // Ne pas créer de contrat après la sortie de flotte.
            $end = Carbon::parse($row['to']);
            if ($vehicle->exit_date !== null && $end->greaterThan($vehicle->exit_date)) {
                continue;
            }

            $start = Carbon::parse($row['from']);
            $duration = $start->diffInDays($end) + 1;
            $type = $duration <= 30 ? ContractType::Lcd : ContractType::Lld;

            // Σ'.6 · ref auto sur les LLD · format CTR-{year}-{plate}-{seq}
            $ref = $row['ref'] ?? null;
            if ($ref === null && $type === ContractType::Lld) {
                $year = $start->year;
                $refSeqByCompany[$company->id] = ($refSeqByCompany[$company->id] ?? 0) + 1;
                $ref = sprintf('CTR-%d-%s-%03d', $year, $company->short_code, $refSeqByCompany[$company->id]);
            }

            // Overlap-tolerant: the anti-overlap trigger may reject a row;
            // log and continue instead of aborting the whole seed.
            try {
                Contract::create([
                    'vehicle_id' => $vehicle->id,
                    'company_id' => $company->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'contract_reference' => $ref,
                    'contract_type' => $type,
                    'notes' => $row['notes'] ?? null,
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'overlapping period')) {
                    $this->command?->warn(sprintf(
                        'Skipped overlap · %s × %s · %s → %s',
                        $row['plate'],
                        $row['company'],
                        $row['from'],
                        $row['to'],
                    ));

                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Seed unavailabilities exercising ADR-0016 rev. 1.1 (R-2024-008):
     * standalone, contract-overlapping, mixed (contract + VFC switch) and
     * non-reducing entries used as anti-regression guards.
     *
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedUnavailabilities(array $vehicles): void
    {
        VehicleEvent::query()->forceDelete();

        // Standalone (out of any contract).

        // EJ-010-JJ Kangoo TPMR: 8-day reducing pound period before the first COR contract.
        $this->createVehicleEvent(
            vehicle: $vehicles['EJ-010-JJ'],
            title: 'Mise en fourrière',
            categories: ['Fourrière (demande publique)'],
            startDate: '2024-02-12',
            endDate: '2024-02-19',
            description: 'Stationnement gênant signalé par la mairie.',
        );

        // EI-009-II Ford Transit - créneau libre 06-01 → 09-30 (entre BTP et ECO).
        // Interdiction de circuler post-sinistre 12 j, réductrice.
        $this->createVehicleEvent(
            vehicle: $vehicles['EI-009-II'],
            title: 'Interdiction de circuler',
            categories: ['Sinistre avec interdiction de circuler'],
            startDate: '2024-07-08',
            endDate: '2024-07-19',
            description: 'Choc latéral, expertise + interdiction préfectorale.',
        );

        // EG-007-GG BMW Série 5 - créneau hors contrats. Suspension CI 25 j,
        // réductrice (max BOFiP § 50).
        $this->createVehicleEvent(
            vehicle: $vehicles['EG-007-GG'],
            title: 'Suspension du CI',
            categories: ["Suspension du certificat d'immatriculation"],
            startDate: '2024-08-05',
            endDate: '2024-08-29',
            description: 'Suspension administrative du certificat d\'immatriculation.',
        );

        // EH-008-HH Partner - maintenance courante 4 j, NON réductrice
        // (BOFiP § 50 : entretien courant exclu).
        $this->createVehicleEvent(
            vehicle: $vehicles['EH-008-HH'],
            title: 'Entretien courant',
            categories: ['Entretien'],
            startDate: '2024-12-09',
            endDate: '2024-12-12',
            description: 'Révision constructeur + remplacement pneus AV.',
        );

        // Contract-overlapping unavailabilities.

        // EA-001-AA: 11-day CI suspension overlapping the ACM contract: reducing.
        $this->createVehicleEvent(
            vehicle: $vehicles['EA-001-AA'],
            title: 'Suspension du CI',
            categories: ["Suspension du certificat d'immatriculation"],
            startDate: '2024-02-15',
            endDate: '2024-02-25',
            description: 'Cohabitation contrat ACM : suspension administrative pour défaut d\'assurance.',
        );

        // EB-002-BB: 10-day no-circulation overlap on the long BTP contract.
        $this->createVehicleEvent(
            vehicle: $vehicles['EB-002-BB'],
            title: 'Interdiction de circuler',
            categories: ['Sinistre avec interdiction de circuler'],
            startDate: '2024-03-10',
            endDate: '2024-03-19',
            description: 'Cohabitation contrat BTP : choc à l\'arrière, attente expertise.',
        );

        // EE-005-EE: mixed case overlapping COR contract AND a VFC switch (PA 8→7).
        $this->createVehicleEvent(
            vehicle: $vehicles['EE-005-EE'],
            title: 'Suspension du CI',
            categories: ["Suspension du certificat d'immatriculation"],
            startDate: '2024-03-10',
            endDate: '2024-03-22',
            description: 'Cohabitation contrat COR + bascule VFC : suspension CI 13 j à cheval sur 2 versions PA.',
        );

        // EH-008-HH: maintenance overlapping BTP, NON-reducing (regression guard).
        $this->createVehicleEvent(
            vehicle: $vehicles['EH-008-HH'],
            title: 'Entretien courant',
            categories: ['Entretien'],
            startDate: '2024-02-12',
            endDate: '2024-02-15',
            description: 'Cohabitation contrat BTP : entretien courant (NE doit PAS réduire la taxe).',
        );

        // 2025 unavailabilities (~15), including cross-year 2024/2025.

        // Cross-year overlap (per-year count check).
        $this->createVehicleEvent(
            vehicle: $vehicles['EI-009-II'],
            title: 'Mise en fourrière',
            categories: ['Fourrière (demande publique)'],
            startDate: '2024-12-20',
            endDate: '2025-01-10',
            description: 'Cross-année · 12j en 2024 + 10j en 2025.',
        );

        // 15-day public pound on a contracted vehicle.
        $this->createVehicleEvent(
            vehicle: $vehicles['EA-001-AA'],
            title: 'Mise en fourrière',
            categories: ['Fourrière (demande publique)'],
            startDate: '2025-04-05',
            endDate: '2025-04-19',
            description: 'Fourrière publique 15j 2025 · réductrice.',
        );

        // 59-day CI suspension (BOFiP case).
        $this->createVehicleEvent(
            vehicle: $vehicles['EB-002-BB'],
            title: 'Suspension du CI',
            categories: ["Suspension du certificat d'immatriculation"],
            startDate: '2025-02-01',
            endDate: '2025-03-31',
            description: 'Suspension CI 59j non bissextile · cas BOFiP.',
        );

        // 30-day no-circulation on an active LCD vehicle (double-count guard).
        $this->createVehicleEvent(
            vehicle: $vehicles['EM-013-MM'],
            title: 'Interdiction de circuler',
            categories: ['Sinistre avec interdiction de circuler'],
            startDate: '2025-03-01',
            endDate: '2025-03-30',
            description: 'Interdiction circulation 30j 2025 sur véhicule actif LLD.',
        );

        // Non-fiscal maintenance on contracted vehicle (regression guard).
        $this->createVehicleEvent(
            vehicle: $vehicles['EF-006-FF'],
            title: 'Entretien courant',
            categories: ['Entretien'],
            startDate: '2025-06-10',
            endDate: '2025-06-15',
            description: 'Maintenance 5j 2025 · NON réductrice (garde-fou).',
        );

        // Fourrière privée (NON réductrice)
        $this->createVehicleEvent(
            vehicle: $vehicles['EG-007-GG'],
            title: 'Fourrière privée',
            categories: ['Administratif'],
            startDate: '2025-05-12',
            endDate: '2025-05-19',
            description: 'Fourrière privée 7j 2025 · NON réductrice.',
        );

        // Vol simple (NON réductrice)
        $this->createVehicleEvent(
            vehicle: $vehicles['EH-008-HH'],
            title: 'Vol du véhicule',
            categories: ['Vol'],
            startDate: '2025-08-04',
            endDate: '2025-08-18',
            description: 'Vol simple 14j 2025 · NON réductrice.',
        );

        // Contrôle technique 2 jours (événement personnalisé · NON réductrice ;
        // les contrôles réglementaires récurrents sont gérés par le Chantier B).
        $this->createVehicleEvent(
            vehicle: $vehicles['EJ-010-JJ'],
            startDate: '2025-09-08',
            endDate: '2025-09-09',
            description: 'Contrôle technique CT 2j 2025.',
            title: 'Contrôle technique',
            categories: ['Contrôle réglementaire', 'Maintenance préventive'],
            amountCents: 8500,
        );

        // Réparation accident simple (NON réductrice)
        $this->createVehicleEvent(
            vehicle: $vehicles['EL-012-LL'],
            title: 'Réparation après sinistre',
            categories: ['Sinistre'],
            startDate: '2025-07-22',
            endDate: '2025-08-04',
            description: 'Réparation accident 14j 2025 · NON réductrice.',
        );

        // Cumul réductrice + non réductrice sur même véhicule en 2025
        $this->createVehicleEvent(
            vehicle: $vehicles['EQ-017-QQ'],
            title: 'Mise en fourrière',
            categories: ['Fourrière (demande publique)'],
            startDate: '2025-04-01',
            endDate: '2025-04-15',
            description: 'Réductrice 15j · 1/2 cumul EQ-017-QQ',
        );
        $this->createVehicleEvent(
            vehicle: $vehicles['EQ-017-QQ'],
            title: 'Entretien courant',
            categories: ['Entretien'],
            startDate: '2025-05-10',
            endDate: '2025-05-25',
            description: 'NON réductrice 16j · 2/2 cumul EQ-017-QQ',
        );

        // Suspension CI mid-2025 sur véhicule Multi-VFC
        $this->createVehicleEvent(
            vehicle: $vehicles['GK-063-KK'],
            title: 'Suspension du CI',
            categories: ["Suspension du certificat d'immatriculation"],
            startDate: '2025-06-20',
            endDate: '2025-07-08',
            description: 'Suspension CI à cheval bascule VFC E85 01/07/2025.',
        );

        // Fourrière publique pendant LCD (anti double-décompte avec R-2025-021)
        $this->createVehicleEvent(
            vehicle: $vehicles['EM-013-MM'],
            title: 'Mise en fourrière',
            categories: ['Fourrière (demande publique)'],
            startDate: '2025-01-15',
            endDate: '2025-01-20',
            description: 'Réductrice 6j PENDANT LCD · anti double-décompte R-2025-021.',
        );

        // ====================================================================
        // === Indispos 2026 (~15) · scissions + cross-année 2025/2026
        // ====================================================================

        // Cross 2025/2026
        $this->createVehicleEvent(
            vehicle: $vehicles['EA-001-AA'],
            title: 'Mise en fourrière',
            categories: ['Fourrière (demande publique)'],
            startDate: '2025-12-15',
            endDate: '2026-01-08',
            description: 'Cross 2025/2026 · 17j en 2025 + 8j en 2026.',
        );

        // Fourrière publique 30j à cheval scission polluants 01/03/2026
        $this->createVehicleEvent(
            vehicle: $vehicles['EM-013-MM'],
            title: 'Mise en fourrière',
            categories: ['Fourrière (demande publique)'],
            startDate: '2026-02-15',
            endDate: '2026-03-16',
            description: 'Réductrice 30j à cheval scission polluants 01/03/2026.',
        );

        // Suspension CI 45j
        $this->createVehicleEvent(
            vehicle: $vehicles['EB-002-BB'],
            title: 'Suspension du CI',
            categories: ["Suspension du certificat d'immatriculation"],
            startDate: '2026-05-10',
            endDate: '2026-06-23',
            description: 'Suspension CI 45j 2026.',
        );

        // Interdiction circulation 20j sur véhicule LCD cluster (Cluster gaming?)
        $this->createVehicleEvent(
            vehicle: $vehicles['EX-024-XX'],
            title: 'Interdiction de circuler',
            categories: ['Sinistre avec interdiction de circuler'],
            startDate: '2026-08-10',
            endDate: '2026-08-29',
            description: 'Interdiction circulation 20j à cheval cluster LCD.',
        );

        // Maintenance 5j sur véhicule IDF post LF 2026 art. 60
        $this->createVehicleEvent(
            vehicle: $vehicles['EV-022-VV'],
            title: 'Entretien courant',
            categories: ['Entretien'],
            startDate: '2026-07-10',
            endDate: '2026-07-14',
            description: 'Maintenance 5j · NON réductrice.',
        );

        // Vol simple
        $this->createVehicleEvent(
            vehicle: $vehicles['EH-008-HH'],
            title: 'Vol du véhicule',
            categories: ['Vol'],
            startDate: '2026-04-12',
            endDate: '2026-04-25',
            description: 'Vol simple 14j 2026 · NON réductrice.',
        );

        // Suspension CI 59j à cheval 2026 non bissextile
        $this->createVehicleEvent(
            vehicle: $vehicles['EF-006-FF'],
            title: 'Suspension du CI',
            categories: ["Suspension du certificat d'immatriculation"],
            startDate: '2026-02-01',
            endDate: '2026-03-31',
            description: 'Suspension CI 59j 2026 (28+31) non bissextile.',
        );

        // Fourrière publique sur véhicule électrique (test calcul · 0 € malgré indispo)
        $this->createVehicleEvent(
            vehicle: $vehicles['FD-030-DD'],
            title: 'Mise en fourrière',
            categories: ['Fourrière (demande publique)'],
            startDate: '2026-06-05',
            endDate: '2026-06-20',
            description: 'Réductrice sur élec · 0€ malgré indispo (exonération R-XXXX-016).',
        );

        // CT contrôle technique (événement personnalisé · cf. Chantier B)
        $this->createVehicleEvent(
            vehicle: $vehicles['FA-027-AA'],
            startDate: '2026-09-15',
            endDate: '2026-09-16',
            description: 'CT 2026.',
            title: 'Contrôle technique',
            categories: ['Contrôle réglementaire', 'Maintenance préventive'],
            details: ['Passage au contrôle technique', 'Réglage des phares'],
            amountCents: 8500,
        );

        // ----------------------------------------------------------------
        // Carnet de dépenses · échantillon de coûts cohérents avec la
        // nouvelle taxonomie (titre = type, catégorie = vraie famille).
        // ----------------------------------------------------------------

        // Entretien courant avec coût + nature métier ajoutée (Pneus).
        $this->createVehicleEvent(
            vehicle: $vehicles['EH-008-HH'],
            title: 'Entretien courant',
            startDate: '2026-03-04',
            endDate: '2026-03-04',
            description: 'Révision + remplacement pneus hiver.',
            categories: ['Entretien', 'Pneus'],
            details: ['Changement des pneus hiver', 'Vidange', 'Contrôle des niveaux', 'Remplacement filtre habitacle'],
            garage: 'Garage Martin',
            postalCode: '91100',
            amountCents: 74000,
        );

        // Nettoyage complet · type Maintenance/entretien (un lavage y a sa place).
        $this->createVehicleEvent(
            vehicle: $vehicles['EV-022-VV'],
            startDate: '2026-05-20',
            endDate: '2026-05-20',
            description: 'Nettoyage intérieur + extérieur avant remise en location.',
            title: 'Nettoyage complet',
            categories: ['Entretien'],
            amountCents: 9000,
        );

        // Événement personnalisé porteur de coût · catégorie réellement distincte.
        $this->createVehicleEvent(
            vehicle: $vehicles['EF-006-FF'],
            startDate: '2026-02-10',
            endDate: '2026-02-13',
            description: 'Pose covering publicitaire partenaire.',
            title: 'Pose covering publicitaire',
            categories: ['Marketing'],
            amountCents: 156000,
        );

        // Réparation après sinistre avec coût · catégorie Sinistre (auto) + Carrosserie.
        $this->createVehicleEvent(
            vehicle: $vehicles['EL-012-LL'],
            title: 'Réparation après sinistre',
            startDate: '2026-06-02',
            endDate: '2026-06-09',
            description: 'Réparation carrosserie aile avant droite.',
            categories: ['Sinistre', 'Carrosserie'],
            details: ['Remplacement aile avant droite', 'Peinture et vernis'],
            garage: 'Carrosserie Dupont',
            postalCode: '75011',
            amountCents: 210000,
        );

        // Accident réparation sur véhicule E85 (vérifier que abat applique sans interférence)
        $this->createVehicleEvent(
            vehicle: $vehicles['FU-047-UU'],
            title: 'Réparation après sinistre',
            categories: ['Sinistre'],
            startDate: '2026-04-08',
            endDate: '2026-04-22',
            description: 'Réparation accident 15j · NON réductrice + E85 abat actif.',
        );

        // Edge case Σ'.5 · indispo ONGOING (end_date = NULL · durée indéterminée)
        // sur le véhicule GM-065-MM (currentStatus=Maintenance)
        if (isset($vehicles['GM-065-MM'])) {
            $this->createVehicleEvent(
                vehicle: $vehicles['GM-065-MM'],
                title: 'Réparation longue durée',
                startDate: '2026-03-15',
                endDate: null,
                description: 'Réparation longue · expertise litige en cours · sans date de fin connue.',
                categories: ['Sinistre'],
            );
        }
    }

    /**
     * Seed the global regulatory-control catalogue (Chantier B / B1): the
     * contrôle technique, recipe légale VP (1re mise en circulation + 4 ans,
     * puis tous les 2 ans). Saving the definition triggers the fleet-wide
     * `controls_due_from` recompute, so badges and échéances light up on the
     * demo vehicles.
     */
    private function seedControls(): void
    {
        ControlDefinition::query()->forceDelete();

        ControlDefinition::create([
            'name' => 'Contrôle technique',
            'anchor' => ControlAnchor::FirstOriginRegistration,
            'initial_duration_value' => 4,
            'initial_duration_unit' => DurationUnit::Years,
            'cycle_value' => 2,
            'cycle_unit' => DurationUnit::Years,
            'notify_driver' => false,
            'implies_unavailability' => false,
            // Reminder cycle: NULL fields inherit the global settings.
            'reminder_days_before' => null,
            'reminder_on_due_day' => null,
            'reminder_repeat_every_days' => null,
            'is_active' => true,
            'display_order' => 1,
        ]);
    }

    /**
     * @param  list<string>  $categories  Natures of the event (at least one);
     *                                    the reductivity is derived from the
     *                                    frozen catalogue block, like the
     *                                    write-time Actions.
     */
    private function createVehicleEvent(
        Vehicle $vehicle,
        string $title,
        string $startDate,
        ?string $endDate,
        ?string $description = null,
        array $categories = [],
        array $details = [],
        ?string $garage = null,
        ?string $postalCode = null,
        ?int $amountCents = null,
    ): void {
        $hasFiscalImpact = array_intersect(
            array_map(static fn (string $c): string => mb_strtolower(trim($c)), $categories),
            array_map('mb_strtolower', EventNatureCatalog::REDUCTIVE),
        ) !== [];

        $event = VehicleEvent::create([
            'vehicle_id' => $vehicle->id,
            'title' => $title,
            'has_fiscal_impact' => $hasFiscalImpact,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'description' => $description,
            'garage' => $garage,
            'postal_code' => $postalCode,
            'amount_cents' => $amountCents,
        ]);

        foreach (array_values(array_unique($categories)) as $category) {
            $event->categories()->create(['category' => $category]);
        }

        foreach (array_values(array_unique($details)) as $detail) {
            $event->details()->create(['detail' => $detail]);
        }
    }

    /**
     * Plan de contrats 2024 · cap 100j max par contrat (sauf ~5 LLD plein
     * année stratégiques pour tests goldens fiscaux). Privilégie les
     * locations courtes/moyennes (LCD 15-30j et LLD 30-100j).
     *
     * Couvre · LCD particuliers (mois entier, février 29j bissextile,
     * 30j exact, 31j non mois entier), clusters LCD 4 consécutifs,
     * scissions ADR-0022, multi-VFC, exonérations, sorties de flotte.
     *
     * @return list<array{plate:string,company:string,from:string,to:string,notes?:string,ref?:string}>
     */
    private function buildContractPlan2024(): array
    {
        return [
            // ============================================================
            // EA-001-AA · Peugeot 308 WLTP 100g · ACM/BTP/COR/DRS/ECO
            // ============================================================
            ['plate' => 'EA-001-AA', 'company' => 'ACM', 'from' => '2024-01-08', 'to' => '2024-02-29'],   // 53j
            ['plate' => 'EA-001-AA', 'company' => 'BTP', 'from' => '2024-03-04', 'to' => '2024-03-18'],   // 15j LCD
            ['plate' => 'EA-001-AA', 'company' => 'COR', 'from' => '2024-04-02', 'to' => '2024-04-21'],   // 20j LCD
            ['plate' => 'EA-001-AA', 'company' => 'ACM', 'from' => '2024-05-02', 'to' => '2024-06-18'],   // 48j
            ['plate' => 'EA-001-AA', 'company' => 'DRS', 'from' => '2024-07-01', 'to' => '2024-07-05'],   // 5j LCD
            ['plate' => 'EA-001-AA', 'company' => 'ECO', 'from' => '2024-09-09', 'to' => '2024-11-15'],   // 68j

            // ============================================================
            // EB-002-BB · Trafic Diesel · 4 contrats (1 LLD coupé)
            // ============================================================
            ['plate' => 'EB-002-BB', 'company' => 'BTP', 'from' => '2024-01-15', 'to' => '2024-04-22'],   // 99j (capé)
            ['plate' => 'EB-002-BB', 'company' => 'DRS', 'from' => '2024-05-06', 'to' => '2024-05-20'],   // 15j LCD
            ['plate' => 'EB-002-BB', 'company' => 'ACM', 'from' => '2024-06-03', 'to' => '2024-06-28'],   // 26j LCD
            ['plate' => 'EB-002-BB', 'company' => 'BTP', 'from' => '2024-09-02', 'to' => '2024-11-29'],   // 89j

            // ============================================================
            // EC-003-CC · Tesla Model 3 électrique · 0 € · 4 contrats
            // ============================================================
            ['plate' => 'EC-003-CC', 'company' => 'ECO', 'from' => '2024-01-02', 'to' => '2024-04-09'],   // 99j (capé)
            ['plate' => 'EC-003-CC', 'company' => 'COR', 'from' => '2024-04-22', 'to' => '2024-05-03'],   // 12j LCD
            ['plate' => 'EC-003-CC', 'company' => 'ACM', 'from' => '2024-05-06', 'to' => '2024-08-12'],   // 99j (capé)
            ['plate' => 'EC-003-CC', 'company' => 'ECO', 'from' => '2024-09-02', 'to' => '2024-12-09'],   // 99j (capé)

            // ============================================================
            // ED-004-DD · Peugeot 207 NEDC essence · 3 contrats
            // ============================================================
            ['plate' => 'ED-004-DD', 'company' => 'DRS', 'from' => '2024-02-01', 'to' => '2024-05-09'],   // 99j (capé)
            ['plate' => 'ED-004-DD', 'company' => 'BTP', 'from' => '2024-06-10', 'to' => '2024-07-02'],   // 23j LCD
            ['plate' => 'ED-004-DD', 'company' => 'DRS', 'from' => '2024-09-02', 'to' => '2024-12-09'],   // 99j (capé)

            // ============================================================
            // EE-005-EE · Renault 21 PA (multi-VFC) · 3 contrats
            // ============================================================
            ['plate' => 'EE-005-EE', 'company' => 'COR', 'from' => '2024-03-04', 'to' => '2024-03-28'],   // 25j LCD bascule
            ['plate' => 'EE-005-EE', 'company' => 'ACM', 'from' => '2024-07-01', 'to' => '2024-07-26'],   // 26j LCD
            ['plate' => 'EE-005-EE', 'company' => 'ECO', 'from' => '2024-09-02', 'to' => '2024-10-31'],   // 60j bascule

            // ============================================================
            // EF-006-FF · Yaris Hybrid WLTP 95g · 4 contrats
            // ============================================================
            ['plate' => 'EF-006-FF', 'company' => 'ACM', 'from' => '2024-01-02', 'to' => '2024-03-29'],   // 88j
            ['plate' => 'EF-006-FF', 'company' => 'ECO', 'from' => '2024-04-15', 'to' => '2024-07-22'],   // 99j (capé)
            ['plate' => 'EF-006-FF', 'company' => 'COR', 'from' => '2024-08-12', 'to' => '2024-08-30'],   // 19j LCD
            ['plate' => 'EF-006-FF', 'company' => 'BTP', 'from' => '2024-10-07', 'to' => '2024-12-15'],   // 70j

            // ============================================================
            // EG-007-GG · BMW Série 5 Diesel · 4 contrats
            // ============================================================
            ['plate' => 'EG-007-GG', 'company' => 'ECO', 'from' => '2024-02-05', 'to' => '2024-04-25'],   // 80j
            ['plate' => 'EG-007-GG', 'company' => 'DRS', 'from' => '2024-05-06', 'to' => '2024-05-25'],   // 20j LCD
            ['plate' => 'EG-007-GG', 'company' => 'ACM', 'from' => '2024-06-10', 'to' => '2024-08-30'],   // 82j
            ['plate' => 'EG-007-GG', 'company' => 'BTP', 'from' => '2024-10-14', 'to' => '2024-12-15'],   // 63j

            // ============================================================
            // EH-008-HH · Partner Diesel (multi-VFC) · 5 contrats
            // ============================================================
            ['plate' => 'EH-008-HH', 'company' => 'BTP', 'from' => '2024-01-08', 'to' => '2024-03-15'],   // 68j
            ['plate' => 'EH-008-HH', 'company' => 'COR', 'from' => '2024-04-01', 'to' => '2024-04-26'],   // 26j LCD bascule
            ['plate' => 'EH-008-HH', 'company' => 'DRS', 'from' => '2024-05-13', 'to' => '2024-07-31'],   // 80j
            ['plate' => 'EH-008-HH', 'company' => 'BTP', 'from' => '2024-08-15', 'to' => '2024-09-05'],   // 22j LCD bascule
            ['plate' => 'EH-008-HH', 'company' => 'ACM', 'from' => '2024-09-09', 'to' => '2024-11-15'],   // 68j

            // ============================================================
            // EI-009-II · Ford Transit Diesel · 4 contrats (1 coupé)
            // ============================================================
            ['plate' => 'EI-009-II', 'company' => 'ACM', 'from' => '2024-01-15', 'to' => '2024-04-22'],   // 99j (capé)
            ['plate' => 'EI-009-II', 'company' => 'ECO', 'from' => '2024-05-13', 'to' => '2024-07-19'],   // 68j
            ['plate' => 'EI-009-II', 'company' => 'BTP', 'from' => '2024-09-02', 'to' => '2024-09-27'],   // 26j LCD
            ['plate' => 'EI-009-II', 'company' => 'COR', 'from' => '2024-10-07', 'to' => '2024-12-13'],   // 68j

            // ============================================================
            // EJ-010-JJ · Kangoo TPMR handicap · zeroing total · 3 contrats
            // ============================================================
            ['plate' => 'EJ-010-JJ', 'company' => 'COR', 'from' => '2024-03-04', 'to' => '2024-05-31'],   // 89j
            ['plate' => 'EJ-010-JJ', 'company' => 'DRS', 'from' => '2024-06-17', 'to' => '2024-09-23'],   // 99j (capé)
            ['plate' => 'EJ-010-JJ', 'company' => 'ECO', 'from' => '2024-11-04', 'to' => '2024-12-15'],   // 42j

            // ============================================================
            // EL-012-LL · Mégane multi-VFC 102→145g · 3 contrats
            // ============================================================
            ['plate' => 'EL-012-LL', 'company' => 'ACM', 'from' => '2024-02-05', 'to' => '2024-04-30'],   // 86j
            ['plate' => 'EL-012-LL', 'company' => 'BTP', 'from' => '2024-05-13', 'to' => '2024-07-26'],   // 75j
            ['plate' => 'EL-012-LL', 'company' => 'ECO', 'from' => '2024-09-09', 'to' => '2024-11-29'],   // 82j

            // ============================================================
            // EM-013-MM Citroën C3 · LCD particulier + LLD
            // ============================================================
            ['plate' => 'EM-013-MM', 'company' => 'COR', 'from' => '2024-01-04', 'to' => '2024-02-02', 'notes' => 'LCD 30j exact'],
            ['plate' => 'EM-013-MM', 'company' => 'IDF', 'from' => '2024-04-01', 'to' => '2024-06-30'],   // 91j
            ['plate' => 'EM-013-MM', 'company' => 'HEX', 'from' => '2024-08-01', 'to' => '2024-10-31'],   // 92j

            // ============================================================
            // EN-014-NN Fiat 500 · LCD février bissextile + LLD
            // ============================================================
            ['plate' => 'EN-014-NN', 'company' => 'PRO', 'from' => '2024-02-01', 'to' => '2024-02-29', 'notes' => 'LCD mois entier 29j bissextile'],
            ['plate' => 'EN-014-NN', 'company' => 'HEX', 'from' => '2024-04-15', 'to' => '2024-07-22'],   // 99j
            ['plate' => 'EN-014-NN', 'company' => 'TUR', 'from' => '2024-08-15', 'to' => '2024-11-21'],   // 99j

            // ============================================================
            // EO-015-OO VW Polo · LCD mois entier mars + LLD
            // ============================================================
            ['plate' => 'EO-015-OO', 'company' => 'MAG', 'from' => '2024-03-01', 'to' => '2024-03-31', 'notes' => 'LCD mois entier 31j'],
            ['plate' => 'EO-015-OO', 'company' => 'LOG', 'from' => '2024-05-01', 'to' => '2024-08-07'],   // 99j
            ['plate' => 'EO-015-OO', 'company' => 'NOV', 'from' => '2024-09-15', 'to' => '2024-12-15'],   // 92j

            // ============================================================
            // EP-016-PP Mini Cooper · LCD divers + LLD
            // ============================================================
            ['plate' => 'EP-016-PP', 'company' => 'PRO', 'from' => '2024-01-15', 'to' => '2024-04-22'],   // 99j
            ['plate' => 'EP-016-PP', 'company' => 'IDF', 'from' => '2024-06-01', 'to' => '2024-08-31'],   // 92j
            ['plate' => 'EP-016-PP', 'company' => 'HEX', 'from' => '2024-09-15', 'to' => '2024-12-22'],   // 99j

            // ============================================================
            // EQ-017-QQ Audi A1 · LLD jan-mai + Cluster LCD juil-oct
            // ============================================================
            ['plate' => 'EQ-017-QQ', 'company' => 'EOL', 'from' => '2024-01-15', 'to' => '2024-04-22'],   // 99j
            ['plate' => 'EQ-017-QQ', 'company' => 'EOL', 'from' => '2024-05-13', 'to' => '2024-06-20'],   // 39j
            // Cluster 4 LCD HEX
            ['plate' => 'EQ-017-QQ', 'company' => 'HEX', 'from' => '2024-07-01', 'to' => '2024-07-25', 'notes' => 'Cluster LCD 1/4'],
            ['plate' => 'EQ-017-QQ', 'company' => 'HEX', 'from' => '2024-08-02', 'to' => '2024-08-28', 'notes' => 'Cluster LCD 2/4'],
            ['plate' => 'EQ-017-QQ', 'company' => 'HEX', 'from' => '2024-09-02', 'to' => '2024-09-30', 'notes' => 'Cluster LCD 3/4'],
            ['plate' => 'EQ-017-QQ', 'company' => 'HEX', 'from' => '2024-10-05', 'to' => '2024-10-29', 'notes' => 'Cluster LCD 4/4'],

            // ============================================================
            // ER-018-RR BMW Série 1 · LCD 31j non mois entier + LLD
            // ============================================================
            ['plate' => 'ER-018-RR', 'company' => 'IDF', 'from' => '2024-04-05', 'to' => '2024-05-05', 'notes' => 'LCD 31j non mois entier · taxable'],
            ['plate' => 'ER-018-RR', 'company' => 'EOL', 'from' => '2024-06-15', 'to' => '2024-09-22'],   // 99j
            ['plate' => 'ER-018-RR', 'company' => 'MAG', 'from' => '2024-10-10', 'to' => '2024-12-20'],   // 72j

            // ============================================================
            // ET-020-TT Volvo XC40 · 3 contrats
            // ============================================================
            ['plate' => 'ET-020-TT', 'company' => 'NOV', 'from' => '2024-04-01', 'to' => '2024-07-08'],   // 99j
            ['plate' => 'ET-020-TT', 'company' => 'HEX', 'from' => '2024-08-01', 'to' => '2024-09-30'],   // 61j
            ['plate' => 'ET-020-TT', 'company' => 'PRO', 'from' => '2024-10-15', 'to' => '2024-12-22'],   // 69j

            // ============================================================
            // FA-027-AA Land Rover Defender · 3 contrats
            // ============================================================
            ['plate' => 'FA-027-AA', 'company' => 'BAT', 'from' => '2024-03-12', 'to' => '2024-06-18'],   // 99j
            ['plate' => 'FA-027-AA', 'company' => 'COB', 'from' => '2024-07-20', 'to' => '2024-09-30'],   // 73j
            ['plate' => 'FA-027-AA', 'company' => 'NOV', 'from' => '2024-10-20', 'to' => '2024-12-22'],   // 64j

            // ============================================================
            // FB-028-BB Renault Master · 3 contrats
            // ============================================================
            ['plate' => 'FB-028-BB', 'company' => 'COB', 'from' => '2024-01-08', 'to' => '2024-04-15'],   // 99j
            ['plate' => 'FB-028-BB', 'company' => 'LOG', 'from' => '2024-05-15', 'to' => '2024-08-22'],   // 100j
            ['plate' => 'FB-028-BB', 'company' => 'MAG', 'from' => '2024-09-20', 'to' => '2024-12-20'],   // 92j

            // ============================================================
            // FD-030-DD Renault Zoé électrique · 3 contrats
            // ============================================================
            ['plate' => 'FD-030-DD', 'company' => 'TUR', 'from' => '2024-05-01', 'to' => '2024-08-07'],   // 99j
            ['plate' => 'FD-030-DD', 'company' => 'COR', 'from' => '2024-09-01', 'to' => '2024-10-20'],   // 50j
            ['plate' => 'FD-030-DD', 'company' => 'IDF', 'from' => '2024-11-01', 'to' => '2024-12-31'],   // 61j

            // ============================================================
            // FE-031-EE Nissan Leaf électrique · 2 contrats
            // ============================================================
            ['plate' => 'FE-031-EE', 'company' => 'MAG', 'from' => '2024-06-15', 'to' => '2024-09-22'],   // 100j
            ['plate' => 'FE-031-EE', 'company' => 'EOL', 'from' => '2024-10-15', 'to' => '2024-12-22'],   // 69j

            // ============================================================
            // FU-047-UU Captur E85 130g · 2 contrats
            // ============================================================
            ['plate' => 'FU-047-UU', 'company' => 'BTP', 'from' => '2024-04-10', 'to' => '2024-07-18'],   // 100j
            ['plate' => 'FU-047-UU', 'company' => 'HEX', 'from' => '2024-08-15', 'to' => '2024-11-22'],   // 100j

            // ============================================================
            // FV-048-VV Ford Focus E85 100g · 3 contrats
            // ============================================================
            ['plate' => 'FV-048-VV', 'company' => 'ACM', 'from' => '2024-02-01', 'to' => '2024-05-10'],   // 100j
            ['plate' => 'FV-048-VV', 'company' => 'EOL', 'from' => '2024-06-10', 'to' => '2024-09-17'],   // 100j
            ['plate' => 'FV-048-VV', 'company' => 'IDF', 'from' => '2024-10-15', 'to' => '2024-12-22'],   // 69j

            // ============================================================
            // FZ-052-ZZ Caddy TPMR handicap · zeroing · 3 contrats
            // ============================================================
            ['plate' => 'FZ-052-ZZ', 'company' => 'COR', 'from' => '2024-03-04', 'to' => '2024-06-11'],   // 100j
            ['plate' => 'FZ-052-ZZ', 'company' => 'TUR', 'from' => '2024-07-15', 'to' => '2024-10-22'],   // 100j
            ['plate' => 'FZ-052-ZZ', 'company' => 'PRO', 'from' => '2024-11-15', 'to' => '2024-12-31'],   // 47j

            // ============================================================
            // GA-053-AA Toyota Prius Hybride · éligible R-2024-017 · 3 contrats
            // ============================================================
            ['plate' => 'GA-053-AA', 'company' => 'EOL', 'from' => '2024-04-22', 'to' => '2024-07-30'],   // 100j
            ['plate' => 'GA-053-AA', 'company' => 'HEX', 'from' => '2024-08-25', 'to' => '2024-12-02'],   // 100j

            // ============================================================
            // GG-059-GG Peugeot 208 · sortie 2024-08-15 · 1 contrat avant
            // ============================================================
            ['plate' => 'GG-059-GG', 'company' => 'ACM', 'from' => '2024-02-15', 'to' => '2024-05-24'],   // 100j
            ['plate' => 'GG-059-GG', 'company' => 'LOG', 'from' => '2024-06-10', 'to' => '2024-08-14'],   // 66j (exit 08-15)

            // ============================================================
            // GH-060-HH Twingo · sortie 2025-07-20 · 2 contrats 2024
            // ============================================================
            ['plate' => 'GH-060-HH', 'company' => 'NOV', 'from' => '2024-05-01', 'to' => '2024-08-08'],   // 100j
            ['plate' => 'GH-060-HH', 'company' => 'PRO', 'from' => '2024-09-10', 'to' => '2024-12-18'],   // 100j

            // ============================================================
            // Autres véhicules · couverture diversifiée 2024
            // ============================================================
            ['plate' => 'EU-021-UU', 'company' => 'MAG', 'from' => '2024-03-01', 'to' => '2024-06-08'],   // 100j
            ['plate' => 'EV-022-VV', 'company' => 'BAT', 'from' => '2024-05-12', 'to' => '2024-07-20'],   // 70j
            ['plate' => 'EW-023-WW', 'company' => 'COB', 'from' => '2024-04-15', 'to' => '2024-07-23'],   // 100j
            ['plate' => 'EX-024-XX', 'company' => 'BTP', 'from' => '2024-02-10', 'to' => '2024-05-19'],   // 100j
            ['plate' => 'EY-025-YY', 'company' => 'LOG', 'from' => '2024-06-01', 'to' => '2024-09-08'],   // 100j
            ['plate' => 'EZ-026-ZZ', 'company' => 'EOL', 'from' => '2024-04-20', 'to' => '2024-07-28'],   // 100j
            ['plate' => 'FC-029-CC', 'company' => 'IDF', 'from' => '2024-07-15', 'to' => '2024-10-22'],   // 100j
            ['plate' => 'FF-032-FF', 'company' => 'NOV', 'from' => '2024-05-20', 'to' => '2024-08-27'],   // 100j (Nexo H₂)
            ['plate' => 'FG-033-GG', 'company' => 'COR', 'from' => '2024-06-15', 'to' => '2024-09-22'],   // 100j (Master ZE élec)
            ['plate' => 'FH-034-HH', 'company' => 'PRO', 'from' => '2024-03-12', 'to' => '2024-06-19'],   // 100j (NEDC Cat1)
            ['plate' => 'FI-035-II', 'company' => 'TUR', 'from' => '2024-04-10', 'to' => '2024-07-18'],   // 100j (NEDC)
            ['plate' => 'FJ-036-JJ', 'company' => 'DRS', 'from' => '2024-08-15', 'to' => '2024-11-22'],   // 100j (NEDC)
            ['plate' => 'FL-038-LL', 'company' => 'BTP', 'from' => '2024-02-15', 'to' => '2024-05-24'],   // 100j (NEDC Diesel)
            ['plate' => 'FM-039-MM', 'company' => 'COB', 'from' => '2024-09-01', 'to' => '2024-12-09'],   // 100j (Berlingo)
            ['plate' => 'FN-040-NN', 'company' => 'BAT', 'from' => '2024-05-10', 'to' => '2024-08-17'],   // 100j (Kangoo)
            ['plate' => 'FP-042-PP', 'company' => 'ACM', 'from' => '2024-04-25', 'to' => '2024-08-02'],   // 100j (PA vintage 6cv)
            ['plate' => 'FT-046-TT', 'company' => 'PRO', 'from' => '2024-06-10', 'to' => '2024-09-17'],   // 100j (BMW E30)

            // Edge case Σ'.5 · contrats 1 jour exact (LCD très court)
            ['plate' => 'EM-013-MM', 'company' => 'TUR', 'from' => '2024-12-20', 'to' => '2024-12-20', 'notes' => 'Contrat 1j (exempt LCD)'],
            ['plate' => 'EN-014-NN', 'company' => 'NOV', 'from' => '2024-12-22', 'to' => '2024-12-22', 'notes' => 'Contrat 1j (exempt LCD)'],
        ];
    }

    /**
     * Contrats 2025 · couvre scission ADR-0022 01/03/2025 (R-2025-004/004-bis
     * rédactionnel) + cross-année 2024/2025 + nouveaux véhicules + clusters.
     *
     * @return list<array{plate:string,company:string,from:string,to:string,notes?:string,ref?:string}>
     */
    private function buildContractPlan2025(): array
    {
        return [
            // ============================================================
            // Cross-année 2024 → 2025 (qualification LCD ≤30 / > 30)
            // ============================================================
            ['plate' => 'ER-018-RR', 'company' => 'PRO', 'from' => '2024-12-20', 'to' => '2025-01-15', 'notes' => 'LCD à cheval 27j ≤ 30j · exempt'],
            ['plate' => 'ES-019-SS', 'company' => 'TUR', 'from' => '2024-12-15', 'to' => '2025-01-15', 'notes' => 'LCD 32j chevauchant · taxable'],
            ['plate' => 'EU-021-UU', 'company' => 'MAG', 'from' => '2024-11-01', 'to' => '2025-02-08'],   // 100j cross-année

            // ============================================================
            // EA-001-AA · 4 contrats variés 2025
            // ============================================================
            ['plate' => 'EA-001-AA', 'company' => 'IDF', 'from' => '2025-01-15', 'to' => '2025-04-23'],   // 99j
            ['plate' => 'EA-001-AA', 'company' => 'HEX', 'from' => '2025-05-15', 'to' => '2025-08-22'],   // 100j
            ['plate' => 'EA-001-AA', 'company' => 'PRO', 'from' => '2025-09-15', 'to' => '2025-10-20'],   // 36j
            ['plate' => 'EA-001-AA', 'company' => 'EOL', 'from' => '2025-11-01', 'to' => '2025-12-22'],   // 52j

            // ============================================================
            // EB-002-BB Trafic Diesel · 3 contrats
            // ============================================================
            ['plate' => 'EB-002-BB', 'company' => 'BTP', 'from' => '2025-02-15', 'to' => '2025-05-25'],   // 100j
            ['plate' => 'EB-002-BB', 'company' => 'COB', 'from' => '2025-06-15', 'to' => '2025-09-22'],   // 100j
            ['plate' => 'EB-002-BB', 'company' => 'BAT', 'from' => '2025-10-15', 'to' => '2025-12-15'],   // 62j

            // ============================================================
            // EC-003-CC Tesla élec · 4 contrats
            // ============================================================
            ['plate' => 'EC-003-CC', 'company' => 'ECO', 'from' => '2025-01-15', 'to' => '2025-04-24'],   // 100j
            ['plate' => 'EC-003-CC', 'company' => 'MAG', 'from' => '2025-05-15', 'to' => '2025-08-22'],   // 100j
            ['plate' => 'EC-003-CC', 'company' => 'TUR', 'from' => '2025-09-15', 'to' => '2025-12-22'],   // 99j

            // ============================================================
            // EF-006-FF Yaris Hybrid · 3 contrats
            // ============================================================
            ['plate' => 'EF-006-FF', 'company' => 'ACM', 'from' => '2025-02-01', 'to' => '2025-05-11'],   // 100j
            ['plate' => 'EF-006-FF', 'company' => 'NOV', 'from' => '2025-06-15', 'to' => '2025-09-22'],   // 100j
            ['plate' => 'EF-006-FF', 'company' => 'PRO', 'from' => '2025-10-15', 'to' => '2025-12-22'],   // 69j

            // ============================================================
            // EG-007-GG BMW Série 5 · 3 contrats successifs (rotation)
            // ============================================================
            ['plate' => 'EG-007-GG', 'company' => 'ACM', 'from' => '2025-01-15', 'to' => '2025-04-23'],   // 99j
            ['plate' => 'EG-007-GG', 'company' => 'BTP', 'from' => '2025-05-15', 'to' => '2025-08-15'],   // 93j
            ['plate' => 'EG-007-GG', 'company' => 'IDF', 'from' => '2025-09-15', 'to' => '2025-12-15'],   // 92j

            // ============================================================
            // EH-008-HH Partner · 3 contrats
            // ============================================================
            ['plate' => 'EH-008-HH', 'company' => 'BAT', 'from' => '2025-03-04', 'to' => '2025-06-11'],   // 100j
            ['plate' => 'EH-008-HH', 'company' => 'COB', 'from' => '2025-07-15', 'to' => '2025-10-22'],   // 100j

            // ============================================================
            // EJ-010-JJ Kangoo handicap · zeroing · 3 contrats
            // ============================================================
            ['plate' => 'EJ-010-JJ', 'company' => 'COR', 'from' => '2025-01-08', 'to' => '2025-04-17'],   // 100j
            ['plate' => 'EJ-010-JJ', 'company' => 'TUR', 'from' => '2025-05-15', 'to' => '2025-08-22'],   // 100j
            ['plate' => 'EJ-010-JJ', 'company' => 'PRO', 'from' => '2025-09-15', 'to' => '2025-12-22'],   // 99j

            // ============================================================
            // EL-012-LL Mégane (multi-VFC) · 3 contrats
            // ============================================================
            ['plate' => 'EL-012-LL', 'company' => 'HEX', 'from' => '2025-02-15', 'to' => '2025-05-25'],   // 100j
            ['plate' => 'EL-012-LL', 'company' => 'EOL', 'from' => '2025-06-15', 'to' => '2025-09-22'],   // 100j
            ['plate' => 'EL-012-LL', 'company' => 'MAG', 'from' => '2025-10-15', 'to' => '2025-12-22'],   // 69j

            // ============================================================
            // EM-013-MM C3 · 3 contrats
            // ============================================================
            ['plate' => 'EM-013-MM', 'company' => 'LOG', 'from' => '2025-01-15', 'to' => '2025-04-24'],   // 100j
            ['plate' => 'EM-013-MM', 'company' => 'NOV', 'from' => '2025-05-15', 'to' => '2025-08-22'],   // 100j
            ['plate' => 'EM-013-MM', 'company' => 'IDF', 'from' => '2025-09-15', 'to' => '2025-12-22'],   // 99j

            // ============================================================
            // EQ-017-QQ Audi A1 · 3 contrats
            // ============================================================
            ['plate' => 'EQ-017-QQ', 'company' => 'EOL', 'from' => '2025-02-01', 'to' => '2025-05-11'],   // 100j
            ['plate' => 'EQ-017-QQ', 'company' => 'PRO', 'from' => '2025-06-15', 'to' => '2025-09-22'],   // 100j

            // ============================================================
            // ET-020-TT Volvo XC40 · 3 contrats
            // ============================================================
            ['plate' => 'ET-020-TT', 'company' => 'NOV', 'from' => '2025-01-15', 'to' => '2025-04-24'],   // 100j
            ['plate' => 'ET-020-TT', 'company' => 'COB', 'from' => '2025-05-15', 'to' => '2025-08-22'],   // 100j

            // ============================================================
            // Contrats à cheval scission ADR-0022 01/03/2025 (rédactionnel)
            // ============================================================
            ['plate' => 'EV-022-VV', 'company' => 'IDF', 'from' => '2025-01-15', 'to' => '2025-04-24', 'notes' => 'À cheval scission 01/03/2025'],   // 100j
            ['plate' => 'EW-023-WW', 'company' => 'COB', 'from' => '2025-02-15', 'to' => '2025-03-15', 'notes' => 'LCD 29j à cheval scission'],
            ['plate' => 'EX-024-XX', 'company' => 'COR', 'from' => '2025-02-15', 'to' => '2025-03-18', 'notes' => 'LCD 32j à cheval scission · taxable'],

            // ============================================================
            // E85 actif en 2025 (R-2025-023) · 5 véhicules · contrats < 100j
            // ============================================================
            ['plate' => 'FU-047-UU', 'company' => 'BTP', 'from' => '2025-01-15', 'to' => '2025-04-24', 'notes' => 'E85 130g WLTP · abattement actif'],
            ['plate' => 'FU-047-UU', 'company' => 'HEX', 'from' => '2025-05-15', 'to' => '2025-08-22'],
            ['plate' => 'FV-048-VV', 'company' => 'ACM', 'from' => '2025-02-15', 'to' => '2025-05-25', 'notes' => 'E85 100g · abattement 100→60'],
            ['plate' => 'FV-048-VV', 'company' => 'PRO', 'from' => '2025-07-01', 'to' => '2025-10-08'],   // 100j
            ['plate' => 'FW-049-WW', 'company' => 'EOL', 'from' => '2025-03-04', 'to' => '2025-06-11', 'notes' => 'E85 251g · perte abattement plafond'],
            ['plate' => 'FX-050-XX', 'company' => 'HEX', 'from' => '2025-01-15', 'to' => '2025-04-24', 'notes' => 'E85 PA 12 CV · abattement -2 CV'],
            ['plate' => 'FY-051-YY', 'company' => 'IDF', 'from' => '2025-02-01', 'to' => '2025-05-11', 'notes' => 'E85 PA 13 CV · perte abattement'],

            // ============================================================
            // Multi-VFC mid-2025 (activation E85 + changement Cat)
            // ============================================================
            ['plate' => 'GK-063-KK', 'company' => 'DRS', 'from' => '2025-01-15', 'to' => '2025-04-24', 'notes' => 'VFC pré-bascule E85 01/07'],
            ['plate' => 'GK-063-KK', 'company' => 'EOL', 'from' => '2025-06-15', 'to' => '2025-09-22', 'notes' => 'VFC post-bascule E85'],
            ['plate' => 'GL-064-LL', 'company' => 'LOG', 'from' => '2025-01-15', 'to' => '2025-04-24', 'notes' => 'VFC pré-Cat1→MostPolluting'],
            ['plate' => 'GL-064-LL', 'company' => 'NOV', 'from' => '2025-06-15', 'to' => '2025-09-22', 'notes' => 'VFC post-MostPolluting'],

            // ============================================================
            // Véhicules divers · couverture variée 2025
            // ============================================================
            ['plate' => 'EY-025-YY', 'company' => 'MAG', 'from' => '2025-01-15', 'to' => '2025-04-24'],   // 100j
            ['plate' => 'EZ-026-ZZ', 'company' => 'PRO', 'from' => '2025-02-15', 'to' => '2025-05-25'],   // 100j
            ['plate' => 'EZ-026-ZZ', 'company' => 'EOL', 'from' => '2025-09-15', 'to' => '2025-12-23'],   // 100j
            ['plate' => 'FA-027-AA', 'company' => 'BAT', 'from' => '2025-03-15', 'to' => '2025-06-22'],   // 100j
            ['plate' => 'FB-028-BB', 'company' => 'BAT', 'from' => '2025-04-01', 'to' => '2025-07-09'],   // 100j
            ['plate' => 'FB-028-BB', 'company' => 'LOG', 'from' => '2025-08-15', 'to' => '2025-11-22'],   // 100j
            ['plate' => 'FC-029-CC', 'company' => 'COB', 'from' => '2025-03-15', 'to' => '2025-06-22'],   // 100j
            ['plate' => 'FD-030-DD', 'company' => 'TUR', 'from' => '2025-03-15', 'to' => '2025-06-22'],   // 100j élec
            ['plate' => 'FD-030-DD', 'company' => 'COR', 'from' => '2025-09-01', 'to' => '2025-12-09'],   // 100j élec
            ['plate' => 'FE-031-EE', 'company' => 'MAG', 'from' => '2025-03-15', 'to' => '2025-06-22'],   // 100j élec
            ['plate' => 'FF-032-FF', 'company' => 'NOV', 'from' => '2025-04-01', 'to' => '2025-07-09'],   // 100j H₂
            ['plate' => 'FG-033-GG', 'company' => 'LOG', 'from' => '2025-05-15', 'to' => '2025-08-22'],   // 100j Master ZE
            ['plate' => 'FH-034-HH', 'company' => 'TUR', 'from' => '2025-02-20', 'to' => '2025-05-30'],   // 99j NEDC
            ['plate' => 'FI-035-II', 'company' => 'PRO', 'from' => '2025-03-15', 'to' => '2025-06-22'],   // 100j NEDC
            ['plate' => 'FJ-036-JJ', 'company' => 'NOV', 'from' => '2025-05-01', 'to' => '2025-08-08'],   // 100j NEDC
            ['plate' => 'FL-038-LL', 'company' => 'HEX', 'from' => '2025-01-15', 'to' => '2025-04-24'],   // 100j NEDC diesel
            ['plate' => 'FM-039-MM', 'company' => 'EOL', 'from' => '2025-03-01', 'to' => '2025-06-08'],   // 100j Berlingo
            ['plate' => 'FP-042-PP', 'company' => 'ACM', 'from' => '2025-04-15', 'to' => '2025-07-23'],   // 100j PA 6cv
            ['plate' => 'FT-046-TT', 'company' => 'COR', 'from' => '2025-06-01', 'to' => '2025-09-08'],   // 100j BMW E30

            // ============================================================
            // Sorties de flotte 2025 · GH-060-HH (07-20) et GJ-062-JJ (10-31)
            // ============================================================
            ['plate' => 'GH-060-HH', 'company' => 'NOV', 'from' => '2025-01-15', 'to' => '2025-04-24'],   // 100j
            ['plate' => 'GH-060-HH', 'company' => 'PRO', 'from' => '2025-05-15', 'to' => '2025-07-19'],   // 66j (exit 07-20)
            ['plate' => 'GJ-062-JJ', 'company' => 'PRO', 'from' => '2025-01-15', 'to' => '2025-04-24'],   // 100j
            ['plate' => 'GJ-062-JJ', 'company' => 'HEX', 'from' => '2025-07-01', 'to' => '2025-10-08'],   // 100j

            // ============================================================
            // FZ-052-ZZ Caddy handicap · zeroing · 2 contrats
            // ============================================================
            ['plate' => 'FZ-052-ZZ', 'company' => 'COR', 'from' => '2025-02-15', 'to' => '2025-05-25'],   // 100j
            ['plate' => 'FZ-052-ZZ', 'company' => 'TUR', 'from' => '2025-07-15', 'to' => '2025-10-22'],   // 100j

            // ============================================================
            // GA-053-AA Prius (R-2024-017 disparu 2025+) · 2 contrats
            // ============================================================
            ['plate' => 'GA-053-AA', 'company' => 'EOL', 'from' => '2025-01-15', 'to' => '2025-04-24'],   // 100j
            ['plate' => 'GA-053-AA', 'company' => 'HEX', 'from' => '2025-06-15', 'to' => '2025-09-22'],   // 100j
        ];
    }

    /**
     * Contrats 2026 · couvre scission ADR-0022 01/03/2026 (R-2026-014/014-bis
     * MATÉRIEL +30 % polluants) + scission 01/09/2026 rédactionnel + clusters.
     *
     * @return list<array{plate:string,company:string,from:string,to:string,notes?:string,ref?:string}>
     */
    private function buildContractPlan2026(): array
    {
        return [
            // ============================================================
            // Cross-année 2025 → 2026
            // ============================================================
            ['plate' => 'EZ-026-ZZ', 'company' => 'EOL', 'from' => '2025-12-20', 'to' => '2026-01-15', 'notes' => 'LCD cross 2025/2026 · ≤30j exempt'],
            ['plate' => 'FA-027-AA', 'company' => 'BAT', 'from' => '2025-11-15', 'to' => '2026-02-22'],   // 100j cross-année

            // ============================================================
            // EA-001-AA · WLTP 100g · 4 contrats variés 2026
            // ============================================================
            ['plate' => 'EA-001-AA', 'company' => 'IDF', 'from' => '2026-01-15', 'to' => '2026-04-24'],   // 100j
            ['plate' => 'EA-001-AA', 'company' => 'HEX', 'from' => '2026-05-15', 'to' => '2026-08-22'],   // 100j
            ['plate' => 'EA-001-AA', 'company' => 'PRO', 'from' => '2026-09-15', 'to' => '2026-12-23'],   // 100j

            // ============================================================
            // EB-002-BB Trafic Diesel · 3 contrats
            // ============================================================
            ['plate' => 'EB-002-BB', 'company' => 'BTP', 'from' => '2026-01-15', 'to' => '2026-04-24'],   // 100j
            ['plate' => 'EB-002-BB', 'company' => 'COB', 'from' => '2026-05-15', 'to' => '2026-08-22'],   // 100j
            ['plate' => 'EB-002-BB', 'company' => 'BAT', 'from' => '2026-09-15', 'to' => '2026-12-15'],   // 92j

            // ============================================================
            // EC-003-CC Tesla élec · 0 € · 3 contrats
            // ============================================================
            ['plate' => 'EC-003-CC', 'company' => 'ECO', 'from' => '2026-01-15', 'to' => '2026-04-24'],   // 100j
            ['plate' => 'EC-003-CC', 'company' => 'MAG', 'from' => '2026-05-15', 'to' => '2026-08-22'],   // 100j
            ['plate' => 'EC-003-CC', 'company' => 'TUR', 'from' => '2026-09-15', 'to' => '2026-12-23'],   // 100j

            // ============================================================
            // EF-006-FF Yaris Hybrid · 3 contrats
            // ============================================================
            ['plate' => 'EF-006-FF', 'company' => 'ACM', 'from' => '2026-02-01', 'to' => '2026-05-11'],   // 100j
            ['plate' => 'EF-006-FF', 'company' => 'NOV', 'from' => '2026-06-15', 'to' => '2026-09-22'],   // 100j
            ['plate' => 'EF-006-FF', 'company' => 'EOL', 'from' => '2026-10-15', 'to' => '2026-12-22'],   // 69j

            // ============================================================
            // EJ-010-JJ Kangoo handicap · zeroing · 3 contrats
            // ============================================================
            ['plate' => 'EJ-010-JJ', 'company' => 'COR', 'from' => '2026-01-08', 'to' => '2026-04-17'],   // 100j
            ['plate' => 'EJ-010-JJ', 'company' => 'TUR', 'from' => '2026-05-15', 'to' => '2026-08-22'],   // 100j
            ['plate' => 'EJ-010-JJ', 'company' => 'PRO', 'from' => '2026-09-15', 'to' => '2026-12-22'],   // 99j

            // ============================================================
            // EM-013-MM C3 · à cheval scission polluants 01/03/2026
            // ============================================================
            ['plate' => 'EM-013-MM', 'company' => 'HEX', 'from' => '2026-01-15', 'to' => '2026-04-24', 'notes' => 'À cheval scission polluants 01/03/2026'],
            ['plate' => 'EM-013-MM', 'company' => 'LOG', 'from' => '2026-05-15', 'to' => '2026-08-22'],   // 100j
            ['plate' => 'EM-013-MM', 'company' => 'IDF', 'from' => '2026-09-15', 'to' => '2026-12-22'],   // 99j

            // ============================================================
            // EN-014-NN Fiat 500 · à cheval polluants
            // ============================================================
            ['plate' => 'EN-014-NN', 'company' => 'IDF', 'from' => '2026-02-01', 'to' => '2026-05-11', 'notes' => 'À cheval polluants Cat1'],
            ['plate' => 'EN-014-NN', 'company' => 'PRO', 'from' => '2026-06-15', 'to' => '2026-09-22'],

            // ============================================================
            // EO-015-OO VW Polo · LCD 29j à cheval scission
            // ============================================================
            ['plate' => 'EO-015-OO', 'company' => 'LOG', 'from' => '2026-02-20', 'to' => '2026-03-20', 'notes' => 'LCD 29j à cheval scission · exempt'],
            ['plate' => 'EO-015-OO', 'company' => 'MAG', 'from' => '2026-05-01', 'to' => '2026-08-08'],   // 100j

            // ============================================================
            // EP-016-PP Mini Cooper · LCD février 28j + LLD
            // ============================================================
            ['plate' => 'EP-016-PP', 'company' => 'PRO', 'from' => '2026-02-01', 'to' => '2026-02-28', 'notes' => 'LCD février 28j non bissextile'],
            ['plate' => 'EP-016-PP', 'company' => 'IDF', 'from' => '2026-04-15', 'to' => '2026-07-23'],   // 100j
            ['plate' => 'EP-016-PP', 'company' => 'HEX', 'from' => '2026-09-01', 'to' => '2026-12-09'],   // 100j

            // ============================================================
            // EQ-017-QQ Audi A1 · à cheval polluants
            // ============================================================
            ['plate' => 'EQ-017-QQ', 'company' => 'EOL', 'from' => '2026-02-15', 'to' => '2026-05-25', 'notes' => 'À cheval polluants 01/03'],
            ['plate' => 'EQ-017-QQ', 'company' => 'PRO', 'from' => '2026-06-15', 'to' => '2026-09-22'],

            // ============================================================
            // ER-018-RR BMW Série 1 · LCD mois entier mars
            // ============================================================
            ['plate' => 'ER-018-RR', 'company' => 'IDF', 'from' => '2026-03-01', 'to' => '2026-03-31', 'notes' => 'LCD mois entier mars 2026 · exempt'],
            ['plate' => 'ER-018-RR', 'company' => 'MAG', 'from' => '2026-05-15', 'to' => '2026-08-22'],   // 100j

            // ============================================================
            // ES-019-SS · LCD 32j à cheval scission · taxable
            // ============================================================
            ['plate' => 'ES-019-SS', 'company' => 'MAG', 'from' => '2026-02-15', 'to' => '2026-03-18', 'notes' => 'LCD 32j à cheval scission · taxable'],
            ['plate' => 'ES-019-SS', 'company' => 'COB', 'from' => '2026-05-01', 'to' => '2026-08-08'],

            // ============================================================
            // ET-020-TT Volvo XC40 · à cheval scission rédactionnelle 01/09
            // ============================================================
            ['plate' => 'ET-020-TT', 'company' => 'NOV', 'from' => '2026-07-01', 'to' => '2026-10-08', 'notes' => 'À cheval Ordo 2025-1247 01/09/2026'],

            // ============================================================
            // EU-021-UU · à cheval rédactionnel
            // ============================================================
            ['plate' => 'EU-021-UU', 'company' => 'COB', 'from' => '2026-08-15', 'to' => '2026-11-22', 'notes' => 'À cheval rédactionnel polluants'],

            // ============================================================
            // EV-022-VV Captur · IDF post-LF 2026 art. 60 majoration
            // ============================================================
            ['plate' => 'EV-022-VV', 'company' => 'IDF', 'from' => '2026-04-01', 'to' => '2026-07-09', 'notes' => 'IDF post-LF 2026 art. 60'],
            ['plate' => 'EV-022-VV', 'company' => 'HEX', 'from' => '2026-08-15', 'to' => '2026-11-22'],

            // ============================================================
            // EW-023-WW · LCD 30j exact mai
            // ============================================================
            ['plate' => 'EW-023-WW', 'company' => 'COR', 'from' => '2026-05-01', 'to' => '2026-05-30', 'notes' => 'LCD 30j exact'],
            ['plate' => 'EW-023-WW', 'company' => 'BAT', 'from' => '2026-07-01', 'to' => '2026-10-08'],

            // ============================================================
            // EX-024-XX · cluster LCD 4 consécutifs HEX 2026
            // ============================================================
            ['plate' => 'EX-024-XX', 'company' => 'HEX', 'from' => '2026-07-01', 'to' => '2026-07-25', 'notes' => 'Cluster LCD 1/4 2026'],
            ['plate' => 'EX-024-XX', 'company' => 'HEX', 'from' => '2026-08-02', 'to' => '2026-08-28', 'notes' => 'Cluster LCD 2/4 2026'],
            ['plate' => 'EX-024-XX', 'company' => 'HEX', 'from' => '2026-09-02', 'to' => '2026-09-30', 'notes' => 'Cluster LCD 3/4 2026'],
            ['plate' => 'EX-024-XX', 'company' => 'HEX', 'from' => '2026-10-05', 'to' => '2026-10-29', 'notes' => 'Cluster LCD 4/4 2026'],

            // ============================================================
            // EY-025-YY · multi-affectation 3 entreprises
            // ============================================================
            ['plate' => 'EY-025-YY', 'company' => 'ACM', 'from' => '2026-01-01', 'to' => '2026-04-10'],   // 100j
            ['plate' => 'EY-025-YY', 'company' => 'BTP', 'from' => '2026-05-01', 'to' => '2026-08-08'],   // 100j
            ['plate' => 'EY-025-YY', 'company' => 'IDF', 'from' => '2026-09-01', 'to' => '2026-12-09'],   // 100j

            // ============================================================
            // E85 actif 2026 (R-2026-023 reconduit) · gain accru durcissement
            // ============================================================
            ['plate' => 'FU-047-UU', 'company' => 'BTP', 'from' => '2026-01-15', 'to' => '2026-04-24', 'notes' => 'E85 130g · gain accru 2026'],
            ['plate' => 'FU-047-UU', 'company' => 'HEX', 'from' => '2026-05-15', 'to' => '2026-08-22'],
            ['plate' => 'FV-048-VV', 'company' => 'ACM', 'from' => '2026-02-01', 'to' => '2026-05-11', 'notes' => 'E85 100g · 132 € abat 2026'],
            ['plate' => 'FV-048-VV', 'company' => 'PRO', 'from' => '2026-07-01', 'to' => '2026-10-08'],
            ['plate' => 'FX-050-XX', 'company' => 'HEX', 'from' => '2026-01-15', 'to' => '2026-04-24', 'notes' => 'E85 PA 12 CV reconduit'],

            // ============================================================
            // Sortie de flotte 2026 · GI-061-II (exit 2026-04-30)
            // ============================================================
            ['plate' => 'GI-061-II', 'company' => 'TUR', 'from' => '2026-01-15', 'to' => '2026-04-25'],

            // ============================================================
            // Autres véhicules · diversifié 2026
            // ============================================================
            ['plate' => 'FB-028-BB', 'company' => 'COB', 'from' => '2026-01-08', 'to' => '2026-04-17'],   // 100j
            ['plate' => 'FB-028-BB', 'company' => 'LOG', 'from' => '2026-05-15', 'to' => '2026-08-22'],
            ['plate' => 'FD-030-DD', 'company' => 'TUR', 'from' => '2026-01-15', 'to' => '2026-04-24'],   // élec
            ['plate' => 'FD-030-DD', 'company' => 'COR', 'from' => '2026-06-15', 'to' => '2026-09-22'],   // élec
            ['plate' => 'FE-031-EE', 'company' => 'MAG', 'from' => '2026-02-01', 'to' => '2026-05-11'],   // élec
            ['plate' => 'FG-033-GG', 'company' => 'LOG', 'from' => '2026-03-01', 'to' => '2026-06-08'],   // Master ZE
            ['plate' => 'FZ-052-ZZ', 'company' => 'COR', 'from' => '2026-01-15', 'to' => '2026-04-24'],   // handicap
            ['plate' => 'FZ-052-ZZ', 'company' => 'TUR', 'from' => '2026-06-15', 'to' => '2026-09-22'],   // handicap
            ['plate' => 'FC-029-CC', 'company' => 'NOV', 'from' => '2026-02-15', 'to' => '2026-05-25'],
            ['plate' => 'FH-034-HH', 'company' => 'PRO', 'from' => '2026-03-04', 'to' => '2026-06-11'],
            ['plate' => 'FJ-036-JJ', 'company' => 'HEX', 'from' => '2026-04-01', 'to' => '2026-07-09'],
            ['plate' => 'FL-038-LL', 'company' => 'EOL', 'from' => '2026-01-15', 'to' => '2026-04-24'],
            ['plate' => 'FP-042-PP', 'company' => 'IDF', 'from' => '2026-05-01', 'to' => '2026-08-08'],
            ['plate' => 'GA-053-AA', 'company' => 'ACM', 'from' => '2026-01-15', 'to' => '2026-04-24'],   // Prius
            ['plate' => 'GA-053-AA', 'company' => 'EOL', 'from' => '2026-06-15', 'to' => '2026-09-22'],   // Prius
        ];
    }
}
