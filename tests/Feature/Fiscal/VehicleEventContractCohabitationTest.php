<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Actions\Contract\StoreContractAction;
use App\Actions\VehicleEvent\CreateVehicleEventAction;
use App\Actions\VehicleEvent\UpdateVehicleEventAction;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Data\User\VehicleEvent\UpdateVehicleEventData;
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
use App\Models\VehicleEventNature;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FiscalCalculator;
use App\Support\VehicleEvent\EventNatureCatalog;
use Database\Seeders\VehicleEventNatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
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
final class VehicleEventContractCohabitationTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCalculator $calculator;

    private CreateVehicleEventAction $createVehicleEvent;

    private StoreContractAction $storeContract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleEventNatureSeeder::class);
        $this->calculator = $this->app->make(FiscalCalculator::class);
        $this->createVehicleEvent = $this->app->make(CreateVehicleEventAction::class);
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
        // VehicleEventOverlapsContractsException.
        $vehicleEvent = $this->createVehicleEvent->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Mise en fourrière',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: ['Fourrière (demande publique)'],
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);

        // Référence : taxe sans indispo
        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);

        // Avec l'indispo réductrice persistée
        $with = $this->calculator->calculate($vehicle, [$contract], [$vehicleEvent], 2024);

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

        // Action - maintenance 5 j pendant le contrat (nature non
        // réductrice). Ne lève pas non plus.
        $vehicleEvent = $this->createVehicleEvent->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Entretien courant',
            startDate: '2024-04-10',
            endDate: '2024-04-14',
            description: null,
            categories: ['Entretien'],
        ));

        $this->assertFalse($vehicleEvent->has_fiscal_impact);

        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);
        $with = $this->calculator->calculate($vehicle, [$contract], [$vehicleEvent], 2024);

        // Aucun effet sur le calcul fiscal - natures non réductrices
        // cohabitent sans toucher au prorata.
        $this->assertSame($without->totalDue, $with->totalDue);
    }

    #[Test]
    public function symetrie_contrat_cree_sur_indispo_reductrice_existante_active_r_2024_008(): void
    {
        // Setup : véhicule, indispo réductrice (suspension du CI) 10 j pré-existante.
        $vehicle = $this->makeVehicleWltp100Essence();
        $company = Company::factory()->create();

        $vehicleEvent = VehicleEvent::factory()->ciSuspension()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-10',
        ]);

        // Création d'un contrat de 60 j qui englobe la période de l'indispo ·
        // ADR-0019 exige que cette saisie passe sans erreur.
        $contract = $this->storeContract->execute(new StoreContractData(
            vehicleId: $vehicle->id,
            companyId: $company->id,
            startDate: '2024-05-15',
            endDate: '2024-07-13',
            contractReference: null,
            notes: null,
            driverIds: [],
        ));

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-05-15',
            'end_date' => '2024-07-13',
        ]);

        // R-2024-008 active dans le sens « post-création contrat ».
        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);
        $with = $this->calculator->calculate($vehicle, [$contract], [$vehicleEvent], 2024);

        $delta = $without->totalDue - $with->totalDue;
        $this->assertEqualsWithDelta(7.46, $delta, 0.02, 'R-2024-008 doit s\'appliquer indépendamment de l\'ordre temporel des saisies.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reductiveNatureProvider(): array
    {
        $cases = [];

        foreach (EventNatureCatalog::REDUCTIVE as $label) {
            $cases[$label] = [$label];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('reductiveNatureProvider')]
    public function chaque_nature_reductrice_du_bloc_fige_reduit_le_prorata(string $nature): void
    {
        [$vehicle, $contract] = $this->makeVehicleWithSixtyDayContract();

        $vehicleEvent = $this->createVehicleEvent->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Indispo réductrice',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: [$nature],
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);
        $this->assertTrue($vehicleEvent->implies_unavailability);
        $this->assertReducesTenDays($vehicle, $contract, $vehicleEvent);
    }

    #[Test]
    public function la_nature_reductrice_est_reconnue_malgre_la_casse_et_les_espaces(): void
    {
        [$vehicle, $contract] = $this->makeVehicleWithSixtyDayContract();

        $vehicleEvent = $this->createVehicleEvent->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Suspension administrative',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: ["  SUSPENSION DU CERTIFICAT D'IMMATRICULATION  "],
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);
        $this->assertReducesTenDays($vehicle, $contract, $vehicleEvent);
    }

    #[Test]
    public function une_nature_reductrice_parmi_des_non_reductrices_suffit_a_reduire(): void
    {
        [$vehicle, $contract] = $this->makeVehicleWithSixtyDayContract();

        $vehicleEvent = $this->createVehicleEvent->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Sinistre immobilisant',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: ['Sinistre', 'Carrosserie', 'Sinistre avec interdiction de circuler'],
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);
        $this->assertReducesTenDays($vehicle, $contract, $vehicleEvent);
    }

    #[Test]
    public function une_nature_libre_quelconque_ne_touche_jamais_au_prorata(): void
    {
        [$vehicle, $contract] = $this->makeVehicleWithSixtyDayContract();

        $vehicleEvent = $this->createVehicleEvent->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Pose covering',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: ['Covering publicitaire', 'Esthétique'],
        ));

        $this->assertFalse($vehicleEvent->has_fiscal_impact);

        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);
        $with = $this->calculator->calculate($vehicle, [$contract], [$vehicleEvent], 2024);

        $this->assertSame($without->totalDue, $with->totalDue);
    }

    #[Test]
    public function la_bascule_des_natures_a_l_update_suit_le_prorata_dans_les_deux_sens(): void
    {
        [$vehicle, $contract] = $this->makeVehicleWithSixtyDayContract();
        $update = $this->app->make(UpdateVehicleEventAction::class);

        // Créé non réducteur : aucun effet.
        $vehicleEvent = $this->createVehicleEvent->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Immobilisation',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: ['Entretien'],
        ));
        $this->assertFalse($vehicleEvent->has_fiscal_impact);

        // Basculé vers une nature réductrice : le prorata se réduit.
        $vehicleEvent = $update->execute($vehicleEvent->id, new UpdateVehicleEventData(
            title: 'Immobilisation',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: ['Fourrière (demande publique)'],
        ));
        $this->assertTrue($vehicleEvent->has_fiscal_impact);
        $this->assertReducesTenDays($vehicle, $contract, $vehicleEvent);

        // Re-basculé non réducteur : l'effet disparaît.
        $vehicleEvent = $update->execute($vehicleEvent->id, new UpdateVehicleEventData(
            title: 'Immobilisation',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: ['Entretien'],
        ));
        $this->assertFalse($vehicleEvent->has_fiscal_impact);

        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);
        $with = $this->calculator->calculate($vehicle, [$contract], [$vehicleEvent], 2024);
        $this->assertSame($without->totalDue, $with->totalDue);
    }

    #[Test]
    public function le_bloc_reducteur_du_code_fait_foi_meme_sans_catalogue_seede(): void
    {
        // Catalogue vide = seed oublié en prod ; le bloc du code fait foi.
        VehicleEventNature::query()->delete();

        [$vehicle, $contract] = $this->makeVehicleWithSixtyDayContract();

        $vehicleEvent = $this->createVehicleEvent->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Mise en fourrière',
            startDate: '2024-01-15',
            endDate: '2024-01-24',
            description: null,
            categories: ['Fourrière (demande publique)'],
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);
        $this->assertReducesTenDays($vehicle, $contract, $vehicleEvent);
    }

    /**
     * R-2024-008 retire les 10 j d'overlap : (173 + 100) × 10 / 366 ≈ 7,46 €.
     */
    private function assertReducesTenDays(Vehicle $vehicle, Contract $contract, VehicleEvent $vehicleEvent): void
    {
        $without = $this->calculator->calculate($vehicle, [$contract], [], 2024);
        $with = $this->calculator->calculate($vehicle, [$contract], [$vehicleEvent], 2024);

        $delta = $without->totalDue - $with->totalDue;
        $this->assertEqualsWithDelta(7.46, $delta, 0.02, 'R-2024-008 doit retirer ~10 j × tarif jour du total.');
        $this->assertGreaterThan(0.0, $delta);
    }

    /**
     * @return array{Vehicle, Contract}
     */
    private function makeVehicleWithSixtyDayContract(): array
    {
        $vehicle = $this->makeVehicleWltp100Essence();
        $contract = Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => Company::factory()->create()->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-02-29',
        ]);

        return [$vehicle, $contract];
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
