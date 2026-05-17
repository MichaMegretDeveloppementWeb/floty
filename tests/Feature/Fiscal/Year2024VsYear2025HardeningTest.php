<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Enums\Contract\ContractType;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'invariants cross-année · vérifie le **durcissement
 * programmé** des barèmes par LF 2024 art. 97 entre 2024 et 2025
 * (PUSH-4 · audit fiscal renforcé 14/05/2026).
 *
 * Pour un même profil de véhicule, la taxe 2025 doit être **strictement
 * supérieure** à la taxe 2024 sur les véhicules concernés par le
 * durcissement (M1 essence Cat1 avec CO₂ ≥ 10 g/km · barème CO₂ durci).
 *
 * Bornes ·
 * - WLTP 100 g/km · 2024 = 173 €, 2025 = 193 €, +20 € (+11,6 %)
 * - WLTP 150 g/km · 2024 = 1183 €, 2025 = 1433 €, +250 € (+21,1 %)
 *   (avec polluants Cat1 inclus · 2024 = 1283 €, 2025 = 1533 €)
 * - Polluants Cat1 inchangés · 100 €/an (texte L. 421-135 stable
 *   31/12/2023 → 01/03/2026)
 *
 * Garde-fou architectural · l'isolation stricte par année (ADR-0022)
 * implique que 2024 et 2025 ont des classes pipeline distinctes. Si
 * une régression accidentelle copiait par mégarde le barème 2024 vers
 * 2025 (par exemple via un mauvais merge), ce test casserait.
 */
final class Year2024VsYear2025HardeningTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationFiscalEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(DeclarationFiscalEngine::class);
    }

    #[Test]
    public function durcissement_wltp_100g_2024_173_2025_193_essence_cat1_full_year(): void
    {
        $total2024 = $this->totalForProfile(co2: 100, year: 2024);
        $total2025 = $this->totalForProfile(co2: 100, year: 2025);

        // Polluants Cat1 inchangés (100 €) · seule la composante CO₂
        // change. CO₂ 2024 = 173 €, CO₂ 2025 = 193 € → +20 €.
        self::assertEqualsWithDelta(273.0, $total2024, 0.01, '2024 · CO₂ 173 + polluants 100');
        self::assertEqualsWithDelta(293.0, $total2025, 0.01, '2025 · CO₂ 193 + polluants 100');
        self::assertGreaterThan(
            $total2024,
            $total2025,
            'Durcissement LF 2024 · 2025 doit être strictement > 2024 à profil égal.',
        );
    }

    #[Test]
    public function durcissement_wltp_150g_essence_cat1_full_year(): void
    {
        $total2024 = $this->totalForProfile(co2: 150, year: 2024);
        $total2025 = $this->totalForProfile(co2: 150, year: 2025);

        // 2024 · barème (55-14)*1+(63-55)*2+(95-63)*3+(115-95)*4+(135-115)*10
        //        +(150-135)*50 = 41+16+96+80+200+750 = 1183 € · +100 polluants = 1283 €
        // 2025 · barème (50-9)*1+(58-50)*2+(90-58)*3+(110-90)*4+(130-110)*10
        //        +(150-130)*50 = 41+16+96+80+200+1000 = 1433 € · +100 polluants = 1533 €
        self::assertEqualsWithDelta(1283.0, $total2024, 1.0);
        self::assertEqualsWithDelta(1533.0, $total2025, 1.0);
        self::assertGreaterThan($total2024, $total2025);
    }

    #[Test]
    public function durcissement_pa_10cv_essence_cat1_full_year(): void
    {
        $total2024 = $this->totalForProfilePa(cv: 10, year: 2024);
        $total2025 = $this->totalForProfilePa(cv: 10, year: 2025);

        // Barème PA est durci · 10 CV 2024 = 26 250 € (selon BOFiP),
        // 10 CV 2025 = 29 750 €. Polluants Cat1 stable = 100 €.
        self::assertGreaterThan(
            $total2024,
            $total2025,
            'Durcissement PA LF 2024 · 10 CV 2025 strictement > 2024.',
        );
    }

    #[Test]
    public function denominateur_prorata_passe_de_366_a_365_entre_2024_et_2025(): void
    {
        // 2024 = bissextile (366 j), 2025 = non bissextile (365 j).
        // On utilise un contrat 31 jours (> THRESHOLD_DAYS 30, hors
        // mois civil entier pour ne pas être qualifié LCD R-XXXX-021)
        // pour exercer le prorata sans interférence d'exonération.
        // Profil WLTP 100g · 31 jours sur l'année.
        $taxe31j2024 = $this->totalForProfile31Days(co2: 100, year: 2024);
        $taxe31j2025 = $this->totalForProfile31Days(co2: 100, year: 2025);

        // 2024 · 273 × 31/366 = 23,1229… (avec WLTP 100g = 173 €
        // + polluants Cat1 = 100 €)
        // 2025 · 293 × 31/365 = 24,8849…
        // Lot 5 D15 · `companyAnnualTax` arrondit half-up à l'EURO
        // (doctrine CIBS L. 131-1) · delta 1.0 sécurise vs valeur
        // théorique en haute précision.
        self::assertEqualsWithDelta(273.0 * 31 / 366, $taxe31j2024, 1.0);
        self::assertEqualsWithDelta(293.0 * 31 / 365, $taxe31j2025, 1.0);

        // 2025/2024 ≈ 24,88 / 23,12 ≈ 1,076 · durcissement WLTP
        // dominant sur la légère hausse du dénominateur (365 vs 366).
        $ratio = $taxe31j2025 / $taxe31j2024;
        self::assertGreaterThan(
            1.05,
            $ratio,
            'Le ratio 2025/2024 sur 31j doit refléter le durcissement WLTP.',
        );
    }

    #[Test]
    public function polluants_cat1_inchanges_entre_2024_et_2025(): void
    {
        // L. 421-135 est stable 31/12/2023 → 01/03/2026 (la
        // revalorisation LF 2026 art. 58 ne prend effet qu'en mars
        // 2026, hors périmètre 2024+2025).
        $total2024 = $this->totalForProfile(co2: 0, year: 2024);
        $total2025 = $this->totalForProfile(co2: 0, year: 2025);

        // CO₂ = 0 g → tarif barème = 0 €. Reste uniquement polluants
        // Cat1 = 100 € identique sur les 2 années.
        self::assertEqualsWithDelta(100.0, $total2024, 0.01);
        self::assertEqualsWithDelta(100.0, $total2025, 0.01);
    }

    /**
     * Disparition R-2024-017 (Ω.7) · l'exonération hybride
     * conditionnelle 2024 (CIBS L. 421-125 v 31/12/2023) a été
     * supprimée par LF 2024 art. 97 23° (effet 01/01/2025 · réforme
     * de l'article L. 421-125 vers le régime E85).
     *
     * Profil testé · véhicule NonPluginHybrid (sous-jacent essence)
     * WLTP 50 g/km, > 3 ans · éligible R-2024-017 (régime général
     * WLTP ≤ 60 g/km) → CO₂ exempt en 2024, taxe CO₂ due en 2025.
     */
    #[Test]
    public function disparition_r2024_017_hybride_conditionnel_taxe_co2_apparait_en_2025(): void
    {
        $total2024 = $this->totalForProfileHybride(co2: 50, year: 2024);
        $total2025 = $this->totalForProfileHybride(co2: 50, year: 2025);

        // 2024 · CO₂ exempt par R-2024-017 (50 ≤ 60 régime général).
        // Reste polluants Cat1 = 100 €.
        self::assertEqualsWithDelta(100.0, $total2024, 0.01, '2024 · CO₂ exempt par R-2024-017, polluants Cat1 = 100 €.');

        // 2025 · CO₂ barème 2025 sur 50 g/km = (50-9)*1 = 41 €.
        // Polluants Cat1 = 100 €. Total = 141 €.
        self::assertEqualsWithDelta(141.0, $total2025, 0.01, '2025 · pas d\'exonération hybride, CO₂ 41 € + polluants 100 €.');

        self::assertGreaterThan(
            $total2024,
            $total2025,
            'Disparition R-2024-017 · 2025 strictement > 2024 pour profil hybride éligible 2024.',
        );
    }

    /**
     * Apparition R-2025-023 E85 (Ω.7) · l'abattement E85 (CIBS
     * L. 421-125 v 01/01/2025 réformé par LF 2024 art. 97 23°) est
     * une nouveauté 2025. En 2024, le flag `accepts_e85` n'avait
     * aucun effet (R-2024-023 placeholder vide informative).
     *
     * Profil testé · véhicule WLTP 100 g/km essence Cat1 avec
     * `accepts_e85=true`, full year.
     *
     * 2024 · pas d'abattement · CO₂ 173 € + polluants 100 € = 273 €.
     * 2025 · abattement 40 % CO₂ · 100 × 0.60 = 60 g/km · barème
     * 2025 sur 60 = 9×0 + (50-9)×1 + (58-50)×2 + (60-58)×3
     * = 0 + 41 + 16 + 6 = 63 €. Total = 63 + 100 = 163 €.
     *
     * Gain E85 cross-année · 273 - 163 = **110 € en moins** en 2025
     * vs 2024 pour le même véhicule.
     */
    #[Test]
    public function apparition_r2025_023_e85_reduit_la_taxe_co2_en_2025_vs_2024(): void
    {
        $total2024 = $this->totalForProfileE85(co2: 100, year: 2024);
        $total2025 = $this->totalForProfileE85(co2: 100, year: 2025);

        self::assertEqualsWithDelta(273.0, $total2024, 0.01, '2024 · pas d\'abattement E85 (placeholder vide).');
        self::assertEqualsWithDelta(163.0, $total2025, 0.01, '2025 · abattement E85 actif · CO₂ 100 → 60 g/km = 63 €.');

        self::assertLessThan(
            $total2024,
            $total2025,
            'Apparition E85 · 2025 strictement < 2024 pour profil E85 100 g/km.',
        );
    }

    private function totalForProfileHybride(int $co2, int $year): float
    {
        $company = $this->makeCompany();
        $vehicle = $this->makeVehicleHybride($company, $year, $co2);
        $this->makeContract($company, $vehicle, sprintf('%d-01-01', $year), sprintf('%d-12-31', $year));

        return $this->engine->compute($company->id, $year)->totalDue;
    }

    private function totalForProfileE85(int $co2, int $year): float
    {
        $company = $this->makeCompany();
        $vehicle = $this->makeVehicleE85($company, $year, $co2);
        $this->makeContract($company, $vehicle, sprintf('%d-01-01', $year), sprintf('%d-12-31', $year));

        return $this->engine->compute($company->id, $year)->totalDue;
    }

    private function makeVehicleHybride(Company $company, int $year, int $co2): Vehicle
    {
        // Véhicule NonPluginHybrid sous-jacent essence, > 3 ans au
        // 01/01/2024 → éligible R-2024-017 régime général.
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('Y%d-%03d-Y%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Toyota',
            'model' => 'Yaris Hybrid',
            'first_french_registration_date' => Carbon::parse('2020-01-01'),
            'first_origin_registration_date' => Carbon::parse('2020-01-01'),
            'first_economic_use_date' => Carbon::parse('2020-01-01'),
            'acquisition_date' => Carbon::parse('2020-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse(sprintf('%d-01-01', $year)),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::NonPluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'taxable_horsepower' => 5,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleE85(Company $company, int $year, int $co2): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('E%d-%03d-E%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Renault',
            'model' => 'Captur E85',
            'first_french_registration_date' => Carbon::parse(sprintf('%d-01-01', $year - 1)),
            'first_origin_registration_date' => Carbon::parse(sprintf('%d-01-01', $year - 1)),
            'first_economic_use_date' => Carbon::parse(sprintf('%d-01-01', $year - 1)),
            'acquisition_date' => Carbon::parse(sprintf('%d-01-01', $year - 1)),
            'current_status' => VehicleStatus::Active,
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse(sprintf('%d-01-01', $year)),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'accepts_e85' => true,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function totalForProfile(int $co2, int $year): float
    {
        $company = $this->makeCompany();
        $vehicle = $this->makeVehicleWltp($company, $year, $co2);
        $this->makeContract($company, $vehicle, sprintf('%d-01-01', $year), sprintf('%d-12-31', $year));

        return $this->engine->compute($company->id, $year)->totalDue;
    }

    private function totalForProfilePa(int $cv, int $year): float
    {
        $company = $this->makeCompany();
        $vehicle = $this->makeVehiclePa($company, $year, $cv);
        $this->makeContract($company, $vehicle, sprintf('%d-01-01', $year), sprintf('%d-12-31', $year));

        return $this->engine->compute($company->id, $year)->totalDue;
    }

    private function totalForProfile31Days(int $co2, int $year): float
    {
        $company = $this->makeCompany();
        $vehicle = $this->makeVehicleWltp($company, $year, $co2);
        // 31 jours du 02/06 au 02/07 · pas un mois civil entier (juin
        // a 30 jours, juillet 31), pas ≤ 30 jours → pas LCD.
        $this->makeContract(
            $company,
            $vehicle,
            sprintf('%d-06-02', $year),
            sprintf('%d-07-02', $year),
        );

        return $this->engine->compute($company->id, $year)->totalDue;
    }

    private function makeCompany(): Company
    {
        return Company::factory()->create();
    }

    private function makeVehicleWltp(Company $company, int $year, int $co2): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('H%d-%03d-H%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Peugeot',
            'model' => '308',
            'first_french_registration_date' => Carbon::parse(sprintf('%d-01-01', $year - 2)),
            'first_origin_registration_date' => Carbon::parse(sprintf('%d-01-01', $year - 2)),
            'first_economic_use_date' => Carbon::parse(sprintf('%d-01-01', $year - 2)),
            'acquisition_date' => Carbon::parse(sprintf('%d-01-01', $year - 2)),
            'current_status' => VehicleStatus::Active,
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse(sprintf('%d-01-01', $year)),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeVehiclePa(Company $company, int $year, int $cv): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('P%d-%03d-P%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Vintage',
            'model' => 'Pre-2006',
            'first_french_registration_date' => Carbon::parse('2003-01-01'),
            'first_origin_registration_date' => Carbon::parse('2003-01-01'),
            'first_economic_use_date' => Carbon::parse('2003-01-01'),
            'acquisition_date' => Carbon::parse('2003-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse(sprintf('%d-01-01', $year)),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'taxable_horsepower' => $cv,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeContract(Company $company, Vehicle $vehicle, string $start, string $end): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);
        $contract->save();

        return $contract;
    }
}
