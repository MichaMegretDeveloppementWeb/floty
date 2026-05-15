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
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test feature end-to-end de la déclaration fiscale 2026.
 *
 * Couvre un cycle annuel complet · plusieurs véhicules de profils
 * variés, contrats LCD + LLD, exemptions multiples, abattement E85,
 * indispos réductrices, scission matérielle polluants 01/03/2026.
 * Valide la cohérence du snapshot émis par {@see DeclarationFiscalEngine}.
 *
 * **Spécificités 2026** ·
 * - Barèmes CO₂ WLTP/NEDC/PA durcis (LF 2024 art. 97-20°) ·
 *   - WLTP · 100 g/km = 213 €, 130 g/km = 683 €.
 *   - Tranches WLTP · (0,4](0€/g), (4,45](1), (45,53](2), (53,85](3),
 *     (85,105](4), (105,125](10), (125,145](50), (145,165](60), (165+)(65).
 * - Scission matérielle polluants 01/03/2026 (LF 2026 art. 58 V IV +30 %) ·
 *   - Cat1 LLD full-year · (100 × 59 + 130 × 306) / 365 = **125,15 €**.
 *   - MostPolluting LLD full-year · (500 × 59 + 650 × 306) / 365 = 625,75 €.
 *   - E inchangé · 0 €.
 * - E85 (R-2026-023) · -40 % sur CO₂ ≤ 250 g/km.
 * - Année non bissextile · dénominateur 365 jours.
 */
final class Year2026DeclarationEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationFiscalEngine $engine;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(DeclarationFiscalEngine::class);
        $this->company = Company::factory()->create([
            'short_code' => 'AC6',
            'legal_name' => 'AC6 SARL 2026',
        ]);
    }

    #[Test]
    public function scenario_realiste_2026_avec_4_vehicules_calcule_le_total_attendu(): void
    {
        // V1 · M1 WLTP 100 g essence Cat1 · LLD full year ·
        //     CO₂ = 213 € (durci 2026) + polluants pondérés 125,15 €
        //     = 338,15 €
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2026-01-01', '2026-12-31', ContractType::Lld);

        // V2 · M1 WLTP 130 g E85 essence Cat1 · LLD full year
        //     E85 abat 130 → 78 g · barème 2026 sur 78 ·
        //     (45-4)*1 + (53-45)*2 + (78-53)*3 = 41 + 16 + 75 = 132 € CO₂
        //     + polluants pondérés 125,15 € = 257,15 €
        $v2 = $this->makeVehicle(
            co2Wltp: 130,
            category: PollutantCategory::Category1,
            acceptsE85: true,
        );
        $this->makeContract($v2, '2026-01-01', '2026-12-31', ContractType::Lld);

        // V3 · Électrique → 0 € pour les 2 taxes (R-2026-016 + Cat E)
        $v3 = $this->makeVehicle(
            co2Wltp: 0,
            category: PollutantCategory::E,
            energySource: EnergySource::Electric,
        );
        $this->makeContract($v3, '2026-01-01', '2026-12-31', ContractType::Lld);

        // V4 · N1 fourgon marchandises → hors taxes (R-2026-004)
        $v4 = $this->makeVehicleN1Fourgon();
        $this->makeContract($v4, '2026-01-01', '2026-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2026);

        // Total = 338,15 (V1) + 257,15 (V2) + 0 (V3) + 0 (V4) = 595,30 €
        self::assertEqualsWithDelta(595.30, $snapshot->totalDue, 0.05);
        // CO₂ · V1=213 + V2=132 + V3=0 + V4=0 = 345 €
        self::assertEqualsWithDelta(345.0, $snapshot->co2DueTotal, 0.05);
        // Polluants · V1=125,15 + V2=125,15 + V3=0 + V4=0 = 250,30 €
        self::assertEqualsWithDelta(250.30, $snapshot->pollutantsDueTotal, 0.05);

        // 4 contrats taxables (V4 inclus mais taxe = 0).
        self::assertCount(4, $snapshot->contractBreakdown);
    }

    #[Test]
    public function breakdown_par_contrat_2026_somme_au_total_au_centime_pres(): void
    {
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2026-01-01', '2026-06-30', ContractType::Lld);
        $this->makeContract($v1, '2026-07-01', '2026-12-31', ContractType::Lld);

        $v2 = $this->makeVehicle(co2Wltp: 130, category: PollutantCategory::MostPolluting);
        $this->makeContract($v2, '2026-04-01', '2026-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2026);

        // Somme des parts contractuelles = total snapshot.
        $sumParts = 0.0;
        foreach ($snapshot->contractBreakdown as $entry) {
            $sumParts += $entry->totalDue;
        }
        self::assertEqualsWithDelta($snapshot->totalDue, $sumParts, 0.05);
        self::assertEqualsWithDelta(
            $snapshot->totalDue,
            $snapshot->co2DueTotal + $snapshot->pollutantsDueTotal,
            0.05,
        );

        self::assertCount(3, $snapshot->contractBreakdown);
    }

    #[Test]
    public function e85_avec_lcd_court_2026_garde_zero_pour_le_lcd_exempte(): void
    {
        // V1 · M1 WLTP 130 g E85 · LCD 20 jours + LLD partial.
        // LCD ≤ 30 j → exempté R-2026-021 → 0 € pour ce contrat.
        // E85 réduit 130 → 78 g WLTP.
        $v1 = $this->makeVehicle(
            co2Wltp: 130,
            category: PollutantCategory::Category1,
            acceptsE85: true,
        );
        $lcd = $this->makeContract($v1, '2026-01-01', '2026-01-20', ContractType::Lcd);
        $lld = $this->makeContract($v1, '2026-02-01', '2026-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2026);

        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }

        // LCD exempté → 0 € + mention exemption.
        self::assertSame(0.0, $byContractId[$lcd->id]->totalDue);
        self::assertSame(
            'Exonéré R-2026-021 · LCD courte durée (CIBS L. 421-129)',
            $byContractId[$lcd->id]->exemptionReason,
        );

        // LLD ramasse l'intégralité de la taxe couple, ≠ 0.
        self::assertGreaterThan(0.0, $byContractId[$lld->id]->totalDue);
        self::assertEqualsWithDelta(
            $snapshot->totalDue,
            $byContractId[$lld->id]->totalDue,
            0.05,
        );
    }

    #[Test]
    public function indispo_reductrice_fourriere_publique_2026_reduit_la_taxe_au_prorata_journalier(): void
    {
        // V1 · M1 WLTP 100 g essence Cat1 · LLD full year + fourrière
        // publique 30 j · 30/365 retirés du numérateur ·
        // taxe ≈ (213 + 125,15) × 335/365 = 338,15 × 335/365 ≈ 310,36 €
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2026-01-01', '2026-12-31', ContractType::Lld);

        Unavailability::factory()->poundPublic()->create([
            'vehicle_id' => $v1->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-30',
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2026);

        $expected = 338.15 * 335 / 365;
        self::assertEqualsWithDelta($expected, $snapshot->totalDue, 0.5);
    }

    #[Test]
    public function scission_polluants_2026_visible_sur_cat1_lld_full_year(): void
    {
        // Véhicule Cat1 LLD du 01/01 au 31/12/2026 ·
        // polluants = (100 × 59 + 130 × 306) / 365 = 125,15 €
        // CO₂ = 0 € (véhicule diesel taxable plus polluants exclu de CO₂
        // par cascade ? Non · Cat1 essence Euro 6 WLTP 100 g/km → CO₂ 213 €).
        // Total attendu = 213 + 125,15 = 338,15 €.
        //
        // Cette assertion garantit que le FiscalSegmentedExecutor segmente
        // bien le calcul polluants par période d'applicabilité R-2026-014 /
        // R-2026-014-bis et obtient la moyenne pondérée 125,15.
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2026-01-01', '2026-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2026);

        // Polluants pondérés (100 × 59 + 130 × 306) / 365 = 125,150684...
        self::assertEqualsWithDelta(125.15, $snapshot->pollutantsDueTotal, 0.05);
        self::assertEqualsWithDelta(213.0, $snapshot->co2DueTotal, 0.05);
        self::assertEqualsWithDelta(338.15, $snapshot->totalDue, 0.05);
    }

    #[Test]
    public function lcd_court_a_cheval_2025_2026_reste_exempte_en_declaration_2026(): void
    {
        // LCD 2025-12-20 → 2026-01-15 · 27 jours total ≤ 30 → qualifié
        // LCD (R-2026-021 sur durée du contrat individuel).
        // La déclaration 2026 ne voit que les 15 jours en 2026, exemptés.
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2025-12-20', '2026-01-15', ContractType::Lcd);

        $snapshot = $this->engine->compute($this->company->id, 2026);

        self::assertSame(0.0, $snapshot->totalDue);
        self::assertCount(1, $snapshot->contractBreakdown);
        self::assertSame(0.0, $snapshot->contractBreakdown[0]->totalDue);
    }

    private function makeVehicle(
        int $co2Wltp,
        PollutantCategory $category,
        EnergySource $energySource = EnergySource::Gasoline,
        bool $acceptsE85 = false,
        bool $handicapAccess = false,
    ): Vehicle {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('Z%d-%03d-Z%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Peugeot',
            'model' => '308',
            'first_french_registration_date' => Carbon::parse('2024-01-01'),
            'first_origin_registration_date' => Carbon::parse('2024-01-01'),
            'first_economic_use_date' => Carbon::parse('2024-01-01'),
            'acquisition_date' => Carbon::parse('2024-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2025-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => $energySource,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => $category,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2Wltp,
            'taxable_horsepower' => 6,
            'handicap_access' => $handicapAccess,
            'accepts_e85' => $acceptsE85,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleN1Fourgon(): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('Z%d-%03d-FG%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Renault',
            'model' => 'Master',
            'first_french_registration_date' => Carbon::parse('2024-01-01'),
            'first_origin_registration_date' => Carbon::parse('2024-01-01'),
            'first_economic_use_date' => Carbon::parse('2024-01-01'),
            'acquisition_date' => Carbon::parse('2024-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2025-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::LightTruck,
            'seats_count' => 3,
            'energy_source' => EnergySource::Diesel,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 180,
            'taxable_horsepower' => 8,
            'handicap_access' => false,
            'n1_removable_second_row_seat' => false,
            'n1_passenger_transport' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeContract(Vehicle $vehicle, string $start, string $end, ContractType $type): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => $type->value,
            'notes' => null,
        ], true);
        $contract->save();

        return $contract;
    }
}
