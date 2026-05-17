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
 * Test feature end-to-end de la déclaration fiscale 2025
 * (AUDIT-A · audit fiscal renforcé 14/05/2026).
 *
 * Couvre un cycle annuel complet · plusieurs véhicules de profils
 * variés, contrats LCD + LLD, exemptions multiples, abattement E85,
 * indispos réductrices, sortie de flotte mid-year, contrats à cheval
 * 2024/2025. Valide la cohérence du snapshot émis par
 * {@see DeclarationFiscalEngine}.
 *
 * Les golden values CO₂ sont calculées manuellement avec le barème
 * WLTP durci 2025 (LF 2024 art. 97, 19°) ·
 *   - Tranches WLTP · 0-9 (0€/g), 9-50 (1), 50-58 (2), 58-90 (3),
 *     90-110 (4), 110-130 (10), 130-150 (50), 150-170 (60), 170+ (65).
 *   - Polluants Cat1 = 100 €/an, MostPolluting = 500 €/an, E = 0 €.
 *   - E85 = abattement 40 % sur CO₂ ≤ 250 g/km (R-2025-023).
 *
 * Dénominateur prorata 2025 · 365 jours (non bissextile).
 */
final class Year2025DeclarationEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationFiscalEngine $engine;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(DeclarationFiscalEngine::class);
        $this->company = Company::factory()->create([
            'short_code' => 'AC2',
            'legal_name' => 'AC2 SARL 2025',
        ]);
    }

    #[Test]
    public function scenario_realiste_2025_avec_4_vehicules_calcule_le_total_attendu(): void
    {
        // V1 · M1 WLTP 100 g essence Cat1 · LLD full year · base CO₂ 193 €
        //     + polluants 100 € = 293 € (BOFiP exemple WLTP 100 g)
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2025-01-01', '2025-12-31', ContractType::Lld);

        // V2 · M1 WLTP 130 g E85 essence Cat1 · LLD full year
        //     E85 abat 130 → 78 g · barème 78 g = (50-9)*1 + (58-50)*2
        //     + (78-58)*3 = 41 + 16 + 60 = 117 € · polluants 100 €
        //     → total 217 €
        $v2 = $this->makeVehicle(
            co2Wltp: 130,
            category: PollutantCategory::Category1,
            acceptsE85: true,
        );
        $this->makeContract($v2, '2025-01-01', '2025-12-31', ContractType::Lld);

        // V3 · Électrique → 0 € pour les 2 taxes (R-2025-016)
        $v3 = $this->makeVehicle(
            co2Wltp: 0,
            category: PollutantCategory::E,
            energySource: EnergySource::Electric,
        );
        $this->makeContract($v3, '2025-01-01', '2025-12-31', ContractType::Lld);

        // V4 · N1 fourgon marchandises → hors taxes (R-2025-004 cascade)
        $v4 = $this->makeVehicleN1Fourgon();
        $this->makeContract($v4, '2025-01-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        // Total attendu · 293 (V1) + 217 (V2) + 0 (V3) + 0 (V4) = 510 €
        self::assertEqualsWithDelta(510.0, $snapshot->totalDue, 0.01);
        self::assertEqualsWithDelta(310.0, $snapshot->co2DueTotal, 0.01);
        self::assertEqualsWithDelta(200.0, $snapshot->pollutantsDueTotal, 0.01);

        // 4 contrats taxables (V4 inclus mais taxe = 0).
        self::assertCount(4, $snapshot->contractBreakdown);
    }

    #[Test]
    public function breakdown_par_contrat_2025_somme_au_total_au_centime_pres(): void
    {
        // Setup mixte avec plusieurs contrats par véhicule pour
        // exercer la répartition par jours taxables.
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2025-01-01', '2025-06-30', ContractType::Lld);
        $this->makeContract($v1, '2025-07-01', '2025-12-31', ContractType::Lld);

        $v2 = $this->makeVehicle(co2Wltp: 130, category: PollutantCategory::MostPolluting);
        $this->makeContract($v2, '2025-04-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        // Somme des parts contractuelles ≈ total snapshot. Lot 5 D15 ·
        // `snapshot->totalDue` arrondi half-up à l'EURO (doctrine
        // CIBS L. 131-1), lignes contrat au centime · écart théorique
        // ≤ 0,50 € · delta 1.0 sécurise.
        $sumParts = 0.0;
        foreach ($snapshot->contractBreakdown as $entry) {
            $sumParts += $entry->totalDue;
        }
        self::assertEqualsWithDelta($snapshot->totalDue, $sumParts, 1.0);
        self::assertEqualsWithDelta(
            $snapshot->totalDue,
            $snapshot->co2DueTotal + $snapshot->pollutantsDueTotal,
            1.0,
        );

        // 3 contrats au breakdown.
        self::assertCount(3, $snapshot->contractBreakdown);
    }

    #[Test]
    public function e85_avec_lcd_court_garde_zero_pour_le_lcd_exempte(): void
    {
        // V1 · M1 WLTP 130 g E85 · LCD 20 jours + LLD partial.
        // - LCD 20 j ≤ 30 j → exempté R-2025-021 → 0 € pour ce contrat.
        // - LLD ramasse l'intégralité de la taxe couple.
        // E85 réduit toujours 130 → 78 g WLTP.
        $v1 = $this->makeVehicle(
            co2Wltp: 130,
            category: PollutantCategory::Category1,
            acceptsE85: true,
        );
        $lcd = $this->makeContract($v1, '2025-01-01', '2025-01-20', ContractType::Lcd);
        $lld = $this->makeContract($v1, '2025-02-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }

        // LCD exempté → 0 € + mention exemption (cf. D5.10.W).
        self::assertSame(0.0, $byContractId[$lcd->id]->totalDue);
        self::assertSame(
            'Exonéré R-2025-021 · LCD courte durée (CIBS L. 421-129)',
            $byContractId[$lcd->id]->exemptionReason,
        );

        // LLD ramasse l'intégralité de la taxe couple, ≠ 0. Delta 1.0
        // pour `snapshot->totalDue` arrondi à l'EURO vs ligne LLD au
        // centime (Lot 5 D15, doctrine CIBS L. 131-1).
        self::assertGreaterThan(0.0, $byContractId[$lld->id]->totalDue);
        self::assertEqualsWithDelta(
            $snapshot->totalDue,
            $byContractId[$lld->id]->totalDue,
            1.0,
        );
    }

    #[Test]
    public function indispo_reductrice_fourriere_publique_2025_reduit_la_taxe_au_prorata_journalier(): void
    {
        // V1 · M1 WLTP 100 g essence Cat1 · LLD full year + indispo
        // fourrière publique 30 j (R-2025-008) → 30/365 de l'année
        // sont retirés du numérateur · taxe = (335/365) × 293.
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2025-01-01', '2025-12-31', ContractType::Lld);

        Unavailability::factory()->poundPublic()->create([
            'vehicle_id' => $v1->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-30',
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        // 30 jours retirés sur 365 → numérateur effectif = 335 j.
        // Base annuelle 293 € · taxe ≈ 293 × 335/365 = 268.91 €
        $expected = 293.0 * 335 / 365;
        self::assertEqualsWithDelta($expected, $snapshot->totalDue, 0.5);
    }

    #[Test]
    public function lcd_court_a_cheval_2024_2025_reste_exempte_en_declaration_2025(): void
    {
        // LCD 2024-12-20 → 2025-01-15 · 27 jours au total ≤ 30 j →
        // qualifié LCD (R-2025-021 sur durée du contrat individuel).
        // Contient 12 j en 2024 et 15 j en 2025. La déclaration 2025
        // ne voit que les 15 j, exemptés en bloc.
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2024-12-20', '2025-01-15', ContractType::Lcd);
        // Pas d'autre contrat 2025 · pas d'assiette taxable du tout
        // → totalDue = 0 €
        $snapshot = $this->engine->compute($this->company->id, 2025);

        self::assertSame(0.0, $snapshot->totalDue);
        // Le contrat à cheval apparait dans le breakdown 2025 avec 0 €.
        self::assertCount(1, $snapshot->contractBreakdown);
        self::assertSame(0.0, $snapshot->contractBreakdown[0]->totalDue);
    }

    #[Test]
    public function lcd_long_a_cheval_2024_2025_taxe_uniquement_jours_2025_au_prorata(): void
    {
        // LCD 2024-12-15 → 2025-01-15 · 32 j > 30 → pas LCD, taxé
        // au prorata. La déclaration 2025 voit 15 j en 2025
        // (01-01 → 01-15). Base 293 € · taxe ≈ 293 × 15/365 = 12.04 €
        // (qui démontre que la déclaration 2025 isole strictement
        // ses jours et n'amalgame pas les 32 jours totaux).
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $this->makeContract($v1, '2024-12-15', '2025-01-15', ContractType::Lcd);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        $expected = 293.0 * 15 / 365;
        // Lot 5 D15 · delta 1.0 pour `snapshot->totalDue` arrondi
        // half-up à l'EURO (doctrine CIBS L. 131-1).
        self::assertEqualsWithDelta($expected, $snapshot->totalDue, 1.0);
    }

    private function makeVehicle(
        int $co2Wltp,
        PollutantCategory $category,
        EnergySource $energySource = EnergySource::Gasoline,
        bool $acceptsE85 = false,
        bool $handicapAccess = false,
    ): Vehicle {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('E%d-%03d-E%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Peugeot',
            'model' => '308',
            'first_french_registration_date' => Carbon::parse('2023-01-01'),
            'first_origin_registration_date' => Carbon::parse('2023-01-01'),
            'first_economic_use_date' => Carbon::parse('2023-01-01'),
            'acquisition_date' => Carbon::parse('2023-01-01'),
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
            'license_plate' => sprintf('F%d-%03d-F%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Renault',
            'model' => 'Master',
            'first_french_registration_date' => Carbon::parse('2023-01-01'),
            'first_origin_registration_date' => Carbon::parse('2023-01-01'),
            'first_economic_use_date' => Carbon::parse('2023-01-01'),
            'acquisition_date' => Carbon::parse('2023-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
        // N1 camionnette sans 2e rangée amovible et non affectée au
        // transport de personnes → hors champ fiscal (cascade R-2025-004).
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
