<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Pipeline;

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
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\VfcSegmentedFiscalExecutor;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Unit du chef d'orchestre VFC segmenté (chantier dette VFC L1).
 *
 * Vérifie la sémantique de segmentation : 1 segment = passe-plat,
 * 2+ segments = additivité, contrat traversant un changement de VFC
 * conserve son verdict LCD basé sur sa durée totale (pas la portion
 * clippée).
 */
final class VfcSegmentedFiscalExecutorTest extends TestCase
{
    use RefreshDatabase;

    private VfcSegmentedFiscalExecutor $executor;

    private FiscalPipeline $pipeline;

    private FiscalYearContext $yearContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = $this->app->make(VfcSegmentedFiscalExecutor::class);
        $this->pipeline = $this->app->make(FiscalPipeline::class);
        $this->yearContext = $this->app->make(FiscalYearContext::class);
    }

    #[Test]
    public function un_segment_couvrant_toute_l_annee_donne_le_meme_resultat_qu_un_appel_direct(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        $contracts = [$this->syntheticContract($vehicle, '2024-01-01', '2024-12-31', ContractType::Lld)];

        $direct = $this->pipeline->execute($this->buildContext($vehicle, $contracts));
        $segmented = $this->executor->execute($this->buildContext($vehicle, $contracts));

        $this->assertSame($direct->daysAssigned, $segmented->daysAssigned);
        $this->assertSame($direct->co2DueRaw, $segmented->co2DueRaw);
        $this->assertSame($direct->pollutantsDueRaw, $segmented->pollutantsDueRaw);
        $this->assertSame($direct->totalDue, $segmented->totalDue);
    }

    #[Test]
    public function deux_vfc_identiques_decoupees_en_segments_donnent_meme_total_qu_une_vfc_unique(): void
    {
        // Référence : 1 véhicule avec 1 seule VFC
        $vehicleSingle = $this->makeVehicleWithSingleVfc();
        $contractsSingle = [$this->syntheticContract($vehicleSingle, '2024-01-01', '2024-12-31', ContractType::Lld)];
        $expected = $this->executor->execute($this->buildContext($vehicleSingle, $contractsSingle));

        // Cible : même profil mais 2 VFCs adjacentes (mêmes valeurs)
        $vehicleSegmented = $this->makeVehicleWithSegmentedVfcsIdentical();
        $contractsSegmented = [$this->syntheticContract($vehicleSegmented, '2024-01-01', '2024-12-31', ContractType::Lld)];
        $actual = $this->executor->execute($this->buildContext($vehicleSegmented, $contractsSegmented));

        // Total de jours identique
        $this->assertSame($expected->daysAssigned, $actual->daysAssigned);
        // Total raw identique au centième près (la somme des prorata
        // partiels équivaut au prorata global puisque les tarifs sont
        // identiques entre segments).
        $this->assertEqualsWithDelta($expected->co2DueRaw, $actual->co2DueRaw, 0.001);
        $this->assertEqualsWithDelta($expected->totalDue, $actual->totalDue, 0.01);
    }

    #[Test]
    public function deux_vfc_avec_co2_differents_produisent_un_total_different_d_une_seule_vfc(): void
    {
        // VFC v1 : WLTP 100 g (tarif annuel 173 €), VFC v2 : WLTP 175 g
        // (tarif annuel beaucoup plus élevé via le barème progressif)
        $vehicle = $this->makeVehicleWithTwoDifferentVfcs(
            v1Co2: 100,
            v2Co2: 175,
            switchDate: '2024-07-01',
        );
        $contracts = [$this->syntheticContract($vehicle, '2024-01-01', '2024-12-31', ContractType::Lld)];

        $segmented = $this->executor->execute($this->buildContext($vehicle, $contracts));

        // Le calcul "actuel" (bug) reviendrait à appliquer uniquement la
        // VFC v2 (la dernière) à toute l'année. Notre exécutant
        // segmenté doit donner un résultat **strictement inférieur**
        // au tarif v2 plein, puisque la moitié de l'année tourne au
        // tarif v1 (moins élevé).
        $vehicleV2Only = $this->makeVehicleWithSingleVfc(co2: 175);
        $contractsV2Only = [$this->syntheticContract($vehicleV2Only, '2024-01-01', '2024-12-31', ContractType::Lld)];
        $v2Only = $this->executor->execute($this->buildContext($vehicleV2Only, $contractsV2Only));

        $this->assertGreaterThan($segmented->co2Due, $v2Only->co2Due,
            'Le calcul segmenté doit donner moins que le calcul "tout v2" puisque le 1er semestre tourne au tarif v1 (moins élevé)');
    }

    #[Test]
    public function contrat_de_35_jours_traversant_un_changement_de_vfc_n_est_pas_qualifie_lcd_a_tort(): void
    {
        // VFC change le 16 juin 2024
        $vehicle = $this->makeVehicleWithTwoDifferentVfcs(
            v1Co2: 100,
            v2Co2: 100,
            switchDate: '2024-06-16',
        );
        // Contrat 35 jours traversant le pivot : 2024-06-01 → 2024-07-05
        // (15 jours en VFC v1 + 20 jours en VFC v2). Au-dessus du seuil
        // LCD (30 j) → ne doit PAS être exonéré LCD.
        $contracts = [$this->syntheticContract($vehicle, '2024-06-01', '2024-07-05', ContractType::Lld)];

        $result = $this->executor->execute($this->buildContext($vehicle, $contracts));

        $this->assertSame(35, $result->daysAssigned, 'les 35 jours doivent rester comptés');
        $this->assertFalse($result->lcdExempt, 'un contrat 35j ne doit PAS être qualifié LCD');
        $this->assertGreaterThan(0.0, $result->co2Due, 'le contrat doit produire une taxe non nulle');
    }

    #[Test]
    public function aucune_vfc_dans_l_annee_throw_missing_fiscal_characteristics(): void
    {
        // Véhicule avec une VFC qui finit AVANT 2024
        $vehicle = Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'Test',
            'first_french_registration_date' => Carbon::parse('2020-01-01'),
            'first_origin_registration_date' => Carbon::parse('2020-01-01'),
            'first_economic_use_date' => Carbon::parse('2020-01-01'),
            'acquisition_date' => Carbon::parse('2020-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2020-01-01'),
            'effective_to' => Carbon::parse('2023-12-31'),
            ...$this->vfcCommonFields(co2: 100),
        ]);

        $this->expectException(FiscalCalculationException::class);
        $this->executor->execute($this->buildContext($vehicle->fresh(), []));
    }

    // --- Helpers --------------------------------------------------------

    /**
     * @param  list<Contract>  $contracts
     */
    private function buildContext(Vehicle $vehicle, array $contracts): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: 2024,
            daysInYear: $this->yearContext->daysInYear(2024),
            contractsForPair: $contracts,
            vehicleUnavailabilitiesInYear: [],
        );
    }

    private function makeVehicleWithSingleVfc(int $co2 = 100): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'Test',
            'first_french_registration_date' => Carbon::parse('2022-01-01'),
            'first_origin_registration_date' => Carbon::parse('2022-01-01'),
            'first_economic_use_date' => Carbon::parse('2022-01-01'),
            'acquisition_date' => Carbon::parse('2022-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);

        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            ...$this->vfcCommonFields(co2: $co2),
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleWithSegmentedVfcsIdentical(): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'Test',
            'first_french_registration_date' => Carbon::parse('2022-01-01'),
            'first_origin_registration_date' => Carbon::parse('2022-01-01'),
            'first_economic_use_date' => Carbon::parse('2022-01-01'),
            'acquisition_date' => Carbon::parse('2022-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);

        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => Carbon::parse('2024-06-30'),
            ...$this->vfcCommonFields(co2: 100),
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-07-01'),
            'effective_to' => null,
            ...$this->vfcCommonFields(co2: 100),
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleWithTwoDifferentVfcs(int $v1Co2, int $v2Co2, string $switchDate): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'Test',
            'first_french_registration_date' => Carbon::parse('2022-01-01'),
            'first_origin_registration_date' => Carbon::parse('2022-01-01'),
            'first_economic_use_date' => Carbon::parse('2022-01-01'),
            'acquisition_date' => Carbon::parse('2022-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);

        $switch = Carbon::parse($switchDate);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => $switch->copy()->subDay(),
            ...$this->vfcCommonFields(co2: $v1Co2),
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $switch,
            'effective_to' => null,
            ...$this->vfcCommonFields(co2: $v2Co2),
        ]);

        return $vehicle->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function vfcCommonFields(int $co2): array
    {
        return [
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
        ];
    }

    private function syntheticContract(Vehicle $vehicle, string $start, string $end, ContractType $type): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'driver_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => $type->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private static int $plateCounter = 0;

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('VFC-%03d-VFC', $n);
    }
}
