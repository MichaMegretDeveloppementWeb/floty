<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Actions\Contract\StoreContractAction;
use App\Actions\Unavailability\CreateUnavailabilityAction;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Enums\Unavailability\UnavailabilityType;
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
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cas-tests permanents d'ADR-0019 - politique de cohabitation
 * indispos ↔ contrats sans contrainte d'overlap.
 *
 * Couvre les invariants minimaux de la décision :
 *
 *  1. Indispo réductrice créée pendant un contrat actif → l'Action ne
 *     bloque pas, et R-2024-008 retire les jours de l'overlap du
 *     numérateur du prorata.
 *  2. Indispo non réductrice créée pendant un contrat actif → l'Action
 *     ne bloque pas, et la taxe annuelle reste strictement identique
 *     au scénario « contrat seul » (preuve qu'aucun double-décompte ni
 *     effet parasite ne s'applique).
 *  3. Symétrie : un contrat créé après coup sur une plage qui couvre
 *     une indispo réductrice pré-existante → `StoreContractAction` ne
 *     bloque pas, et R-2024-008 active comme dans le sens inverse.
 *
 * Ces tests sont des **garde-fous d'architecture** : si quelqu'un
 * réintroduit un check overlap indispo↔contrat, ils tombent
 * immédiatement.
 */
final class UnavailabilityContractCohabitationTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCalculator $calculator;

    private CreateUnavailabilityAction $createUnavailability;

    private StoreContractAction $storeContract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->app->make(FiscalCalculator::class);
        $this->createUnavailability = $this->app->make(CreateUnavailabilityAction::class);
        $this->storeContract = $this->app->make(StoreContractAction::class);
    }

    #[Test]
    public function indispo_reductrice_pendant_contrat_active_r_2024_008(): void
    {
        // Setup : véhicule M1 essence WLTP 100 g/km, contrat de 60 jours
        // (jan-fév 2024) à une entreprise.
        $vehicle = $this->makeVehicleWltp100Essence();
        $company = Company::factory()->create();
        $contract = Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-02-29', // 60 jours sur année bissextile
        ]);

        // Action - création d'une fourrière publique de 10 jours qui
        // chevauche le contrat. Sans ADR-0019, cette ligne lèverait
        // UnavailabilityOverlapsContractsException.
        $unavailability = $this->createUnavailability->execute(new StoreUnavailabilityData(
            vehicleId: $vehicle->id,
            type: UnavailabilityType::PoundPublic,
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
        ));

        $this->assertTrue($unavailability->has_fiscal_impact);

        // Référence : taxe sans indispo
        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);

        // Avec l'indispo réductrice persistée
        $with = $this->calculator->calculate($vehicle, [$contract], [$unavailability], 2024);

        // R-2024-008 doit retirer 10 jours du numérateur du prorata.
        // Tarif plein WLTP 100 g/km essence = 173 € (CO₂) + 100 € (poll cat 1)
        // Sans indispo : (173 + 100) × 60 / 366
        // Avec indispo : (173 + 100) × 50 / 366
        // Delta attendu : (173 + 100) × 10 / 366 ≈ 7,4590…
        $delta = $without->totalDue - $with->totalDue;
        $this->assertEqualsWithDelta(7.46, $delta, 0.02, 'R-2024-008 doit retirer ~10 j × tarif jour du total.');
        $this->assertGreaterThan(0.0, $delta);
    }

    #[Test]
    public function indispo_non_reductrice_pendant_contrat_n_a_aucun_impact_fiscal(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();
        $company = Company::factory()->create();
        $contract = Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-04-01',
            'end_date' => '2024-05-30',
        ]);

        // Action - maintenance 5 j pendant le contrat (type non
        // réducteur). Ne lève pas non plus.
        $unavailability = $this->createUnavailability->execute(new StoreUnavailabilityData(
            vehicleId: $vehicle->id,
            type: UnavailabilityType::Maintenance,
            startDate: '2024-04-10',
            endDate: '2024-04-14',
            description: null,
        ));

        $this->assertFalse($unavailability->has_fiscal_impact);

        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);
        $with = $this->calculator->calculate($vehicle, [$contract], [$unavailability], 2024);

        // Aucun effet sur le calcul fiscal - types non réducteurs
        // cohabitent sans toucher au prorata.
        $this->assertSame($without->totalDue, $with->totalDue);
    }

    #[Test]
    public function symetrie_contrat_cree_sur_indispo_reductrice_existante_active_r_2024_008(): void
    {
        // Setup : véhicule, indispo `ci_suspension` 10 j pré-existante.
        $vehicle = $this->makeVehicleWltp100Essence();
        $company = Company::factory()->create();

        $unavailability = Unavailability::factory()->ciSuspension()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-10',
        ]);

        // Action - création d'un contrat de 60 j qui englobe la
        // période de l'indispo. ADR-0019 D2 : la symétrie applicative
        // exige que cette saisie passe sans erreur.
        $contract = $this->storeContract->execute(new StoreContractData(
            vehicleId: $vehicle->id,
            companyId: $company->id,
            driverId: null,
            startDate: '2024-05-15',
            endDate: '2024-07-13',
            contractReference: null,
            notes: null,
        ));

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-05-15',
            'end_date' => '2024-07-13',
        ]);

        // R-2024-008 active dans le sens « post-création contrat ».
        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);
        $with = $this->calculator->calculate($vehicle, [$contract], [$unavailability], 2024);

        $delta = $without->totalDue - $with->totalDue;
        $this->assertEqualsWithDelta(7.46, $delta, 0.02, 'R-2024-008 doit s\'appliquer indépendamment de l\'ordre temporel des saisies.');
    }

    /**
     * Véhicule M1 essence Euro 6 WLTP 100 g/km cat 1 - la
     * configuration de référence des exemples BOFiP.
     */
    private function makeVehicleWltp100Essence(): Vehicle
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
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 100,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private static int $plateCounter = 0;

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('UCC-%03d-UCC', $n);
    }
}
