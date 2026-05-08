<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal;

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
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FiscalCalculator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la refonte κ.5 : `FiscalCalculator` route à présent via
 * {@see FiscalSegmentedExecutor} (au lieu de `FiscalPipeline` direct).
 * Effet métier : la preview taxes du drawer planning est correcte sur
 * véhicule multi-VFC ; pour mono-VFC, le résultat est strictement
 * identique au pré-κ.5 (no-regression couvert par
 * {@see FiscalCalculatorTest}).
 *
 * Ce fichier vérifie spécifiquement que :
 *   1. mono-VFC : le calculator produit le même résultat que le
 *      segmenteur (sanity, redondance volontaire avec FiscalCalculatorTest).
 *   2. multi-VFC : le calculator propage la segmentation et reproduit
 *      le résultat du segmenteur (la valeur tarifaire dépend du mix VFC).
 *   3. véhicule sans VFC active sur l'année : propagation de
 *      `FiscalCalculationException`.
 */
final class FiscalCalculatorMultiVfcTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCalculator $calculator;

    private FiscalSegmentedExecutor $executor;

    private FiscalYearContext $yearContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->app->make(FiscalCalculator::class);
        $this->executor = $this->app->make(FiscalSegmentedExecutor::class);
        $this->yearContext = $this->app->make(FiscalYearContext::class);
    }

    #[Test]
    public function mono_vfc_le_calculator_produit_le_meme_co2_due_que_le_segmenteur(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc(co2: 100);
        $contracts = [$this->fullYearContract($vehicle)];

        $breakdown = $this->calculator->calculate($vehicle, $contracts, [], 2024);
        $segmented = $this->executor->execute($this->buildContext($vehicle, $contracts));

        self::assertSame($segmented->daysAssigned, $breakdown->daysAssigned);
        self::assertSame($segmented->co2Due, $breakdown->co2Due);
        self::assertSame($segmented->pollutantsDue, $breakdown->pollutantsDue);
        self::assertSame($segmented->totalDue, $breakdown->totalDue);
    }

    #[Test]
    public function multi_vfc_le_calculator_segmente_et_le_resultat_correspond_au_segmenteur(): void
    {
        // VFC v1 : WLTP 100 g/km (tarif modéré). VFC v2 : WLTP 175 g/km
        // (palier supérieur → tarif élevé). Bascule au 01/07/2024.
        // Le résultat correct (segmenté) est strictement entre le tarif
        // tout-100g et le tarif tout-175g.
        $vehicle = $this->makeVehicleWithTwoDifferentVfcs(
            v1Co2: 100,
            v2Co2: 175,
            switchDate: '2024-07-01',
        );
        $contracts = [$this->fullYearContract($vehicle)];

        $breakdown = $this->calculator->calculate($vehicle, $contracts, [], 2024);
        $segmented = $this->executor->execute($this->buildContext($vehicle, $contracts));

        // Le calculator doit être strictement aligné avec le segmenteur.
        self::assertSame($segmented->daysAssigned, $breakdown->daysAssigned);
        self::assertSame($segmented->co2Due, $breakdown->co2Due);
        self::assertSame($segmented->totalDue, $breakdown->totalDue);

        // Sanity : un calcul tout-175g (pré-κ comportement « VFC actuelle »)
        // donnerait un montant plus élevé. Le calculator route ne doit
        // PAS reproduire ce montant erroné.
        $vehicleAllV2 = $this->makeVehicleWithSingleVfc(co2: 175);
        $contractsV2 = [$this->fullYearContract($vehicleAllV2)];
        $allV2 = $this->executor->execute($this->buildContext($vehicleAllV2, $contractsV2));

        self::assertGreaterThan(
            $breakdown->co2Due,
            $allV2->co2Due,
            'preview multi-VFC doit être strictement < tarif tout-VFC2 (puisqu\'une moitié de l\'année tourne à 100g)',
        );
    }

    #[Test]
    public function vehicule_sans_vfc_dans_l_annee_propage_l_exception(): void
    {
        // Véhicule créé sans VFC active sur 2024 (VFC s'arrête en 2023).
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
        $vehicle = $vehicle->fresh();

        $this->expectException(FiscalCalculationException::class);

        $this->calculator->calculate($vehicle, [$this->fullYearContract($vehicle)], [], 2024);
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

    private function makeVehicleWithSingleVfc(int $co2): Vehicle
    {
        $vehicle = $this->makeBareVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            ...$this->vfcCommonFields(co2: $co2),
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleWithTwoDifferentVfcs(int $v1Co2, int $v2Co2, string $switchDate): Vehicle
    {
        $vehicle = $this->makeBareVehicle();
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

    private function makeBareVehicle(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'StubKappa5',
            'first_french_registration_date' => Carbon::parse('2022-01-01'),
            'first_origin_registration_date' => Carbon::parse('2022-01-01'),
            'first_economic_use_date' => Carbon::parse('2022-01-01'),
            'acquisition_date' => Carbon::parse('2022-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
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

    private function fullYearContract(Vehicle $vehicle): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private static int $plateCounter = 0;

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('K5-%03d-K5', $n);
    }
}
