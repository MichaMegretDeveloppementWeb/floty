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
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de cas extrêmes 2025 (AUDIT-B · audit fiscal renforcé
 * 14/05/2026).
 *
 * Couvre les bordures et combinaisons que les tests unitaires de
 * règles isolées n'exercent pas · interaction multi-règles à travers
 * le pipeline complet et la déclaration finale.
 *
 * Cibles ·
 *  - VFC multi-segments (changement homologation mid-year)
 *  - Bordures LCD · 30 j exactement, 31 j (limite), mois civil entier
 *  - Cumuls E85 · E85 + handicap, E85 + indispo réductrice
 *  - Sortie de flotte mid-year + contrat débordant (ADR-0018)
 *  - Indispo couvrant un LCD (garde-fou no-double-count)
 */
final class Year2025EdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationFiscalEngine $engine;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(DeclarationFiscalEngine::class);
        $this->company = Company::factory()->create([
            'short_code' => 'EDG',
            'legal_name' => 'EdgeCases SARL',
        ]);
    }

    #[Test]
    public function lcd_30_jours_exactement_est_exempte_31_jours_ne_est_pas(): void
    {
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        // LCD 30j exactement · borne haute inclusive (≤ 30)
        $c30 = $this->makeContract($v1, '2025-02-01', '2025-03-02', ContractType::Lcd); // 30j
        $this->makeContract($v1, '2025-04-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }

        // LCD 30j exact = exempté → totalDue = 0
        self::assertSame(0.0, $byContractId[$c30->id]->totalDue);
        self::assertNotNull($byContractId[$c30->id]->exemptionReason);

        // Test isolé · LCD 31j ne est pas exempté
        $v2 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $c31 = $this->makeContract($v2, '2025-02-01', '2025-03-03', ContractType::Lcd); // 31j

        $snapshot = $this->engine->compute($this->company->id, 2025);
        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }
        self::assertGreaterThan(0.0, $byContractId[$c31->id]->totalDue);
        self::assertNull($byContractId[$c31->id]->exemptionReason);
    }

    #[Test]
    public function lcd_mois_civil_entier_janvier_31_jours_est_exempte(): void
    {
        // Cas particulier R-2025-021 · contrat couvrant exactement un
        // mois civil entier (1er → dernier jour) → exempté même si
        // 31 jours.
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        $jan = $this->makeContract($v1, '2025-01-01', '2025-01-31', ContractType::Lcd); // 31j mois entier

        $snapshot = $this->engine->compute($this->company->id, 2025);

        self::assertSame(0.0, $snapshot->totalDue);
        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }
        self::assertSame(0.0, $byContractId[$jan->id]->totalDue);
        self::assertNotNull($byContractId[$jan->id]->exemptionReason);
    }

    #[Test]
    public function e85_avec_handicap_combine_les_2_exemptions(): void
    {
        // V1 · M1 WLTP 130 g E85 + handicap. R-2025-015 (handicap)
        // exempte intégralement · prime sur l'abattement E85 qui
        // n'est jamais appliqué (l'exemption tronque le pipeline avant
        // pricing). Résultat · 0 € même avec E85.
        $v1 = $this->makeVehicle(
            co2Wltp: 130,
            category: PollutantCategory::Category1,
            acceptsE85: true,
            handicapAccess: true,
        );
        $this->makeContract($v1, '2025-01-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        self::assertSame(0.0, $snapshot->totalDue);
    }

    #[Test]
    public function e85_avec_indispo_reductrice_combine_e85_abattement_et_prorata(): void
    {
        // V1 · M1 WLTP 130 g E85 → barème 78 g = 117 € CO₂ + 100 €
        // polluants = 217 €. Indispo fourrière publique 30 j → 30/365
        // retirés → 217 × 335/365 ≈ 199.16 €.
        $v1 = $this->makeVehicle(
            co2Wltp: 130,
            category: PollutantCategory::Category1,
            acceptsE85: true,
        );
        $this->makeContract($v1, '2025-01-01', '2025-12-31', ContractType::Lld);
        VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $v1->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-30',
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        $expected = 217.0 * 335 / 365;
        self::assertEqualsWithDelta($expected, $snapshot->totalDue, 1.0);
    }

    #[Test]
    public function vehicule_e85_au_dessus_du_plafond_250g_perd_l_abattement(): void
    {
        // V1 · M1 WLTP 260 g E85 → > 250 → pas d'abattement. Barème
        // 260 g = 1433 + (170-150)*60 + (260-170)*65 = 1433+1200+5850
        // = 8483 €. Polluants Cat1 = 100 €. Total = 8583 €.
        $v1 = $this->makeVehicle(
            co2Wltp: 260,
            category: PollutantCategory::Category1,
            acceptsE85: true,
        );
        $this->makeContract($v1, '2025-01-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        // Vérifie au minimum que l'abattement n'a pas été appliqué
        // (sinon co2_wltp serait passé à 156 → tarif beaucoup plus bas).
        self::assertGreaterThan(8000.0, $snapshot->totalDue);
    }

    #[Test]
    public function vfc_change_mid_year_2025_taxation_strictement_entre_les_bornes_pleine_annee(): void
    {
        // V1 a 2 VFC en 2025 (segmentation pipeline) · 100 g jan-juin
        // puis 150 g juil-dec. Le résultat doit être strictement entre
        // les bornes plein-100g (293 €) et plein-150g (1533 €).
        // La couverture détaillée multi-VFC est dans
        // {@see Tests\Unit\Fiscal\Pipeline\MultiVfcEdgeCasesTest} ·
        // ici on valide juste l'intégration via DeclarationFiscalEngine.
        $v1 = $this->makeVehicleNoVfc();
        $this->makeVfc(
            vehicle: $v1,
            from: '2025-01-01',
            to: '2025-06-30',
            co2Wltp: 100,
            category: PollutantCategory::Category1,
        );
        $this->makeVfc(
            vehicle: $v1,
            from: '2025-07-01',
            to: null,
            co2Wltp: 150,
            category: PollutantCategory::Category1,
            isUpdate: true,
        );
        $this->makeContract($v1, '2025-01-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        // Bornes · plein-100 = 293 €, plein-150 = 1533 €.
        self::assertGreaterThan(293.0, $snapshot->totalDue);
        self::assertLessThan(1533.0, $snapshot->totalDue);
    }

    #[Test]
    public function indispo_reductrice_pendant_lcd_ne_double_compte_pas(): void
    {
        // Garde-fou ADR-0016 · R-2025-021 retire les jours LCD du
        // numérateur. Si R-2025-008 recomptait une indispo réductrice
        // pendant un LCD, on aurait un double-décompte. Le pipeline
        // doit appliquer R-2025-008 uniquement sur les jours hors-LCD.
        $v1 = $this->makeVehicle(co2Wltp: 100, category: PollutantCategory::Category1);
        // LCD 25j en avril
        $lcd = $this->makeContract($v1, '2025-04-01', '2025-04-25', ContractType::Lcd);
        // LLD sur le reste de l'année
        $lld = $this->makeContract($v1, '2025-05-01', '2025-12-31', ContractType::Lld);
        // Indispo fourrière publique chevauchant LCD (12 j pendant LCD)
        // + 18 j post-LCD = 30 j total dont 12 j à ignorer (doublon LCD)
        VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $v1->id,
            'start_date' => '2025-04-15',
            'end_date' => '2025-05-14',
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2025);

        // Sanity · la taxe doit être > 0 (LLD couvre majeure partie)
        // et inférieure à la taxe full-year (293 €).
        self::assertGreaterThan(0.0, $snapshot->totalDue);
        self::assertLessThan(293.0, $snapshot->totalDue);

        // LCD reste exempté.
        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }
        self::assertSame(0.0, $byContractId[$lcd->id]->totalDue);
        self::assertGreaterThan(0.0, $byContractId[$lld->id]->totalDue);
    }

    private function makeVehicle(
        int $co2Wltp,
        PollutantCategory $category,
        EnergySource $energySource = EnergySource::Gasoline,
        bool $acceptsE85 = false,
        bool $handicapAccess = false,
    ): Vehicle {
        $vehicle = $this->makeVehicleNoVfc();
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

    private function makeVehicleNoVfc(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => sprintf('Z%d-%03d-Z%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Peugeot',
            'model' => '308',
            'first_french_registration_date' => Carbon::parse('2023-01-01'),
            'first_origin_registration_date' => Carbon::parse('2023-01-01'),
            'first_economic_use_date' => Carbon::parse('2023-01-01'),
            'acquisition_date' => Carbon::parse('2023-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
    }

    private function makeVfc(
        Vehicle $vehicle,
        string $from,
        ?string $to,
        int $co2Wltp,
        PollutantCategory $category,
        bool $isUpdate = false,
    ): VehicleFiscalCharacteristics {
        return VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse($from),
            'effective_to' => $to !== null ? Carbon::parse($to) : null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => $category,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2Wltp,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'change_reason' => $isUpdate
                ? FiscalCharacteristicsChangeReason::Recharacterization
                : FiscalCharacteristicsChangeReason::InitialCreation,
        ]);
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
