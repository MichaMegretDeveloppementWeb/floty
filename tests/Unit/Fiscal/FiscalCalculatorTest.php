<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal;

use App\Data\User\Fiscal\FiscalBreakdownData;
use App\DTO\Fiscal\FiscalBreakdown;
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
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Filet de sécurité pour `FiscalCalculator::calculate` avant la refonte
 * de la phase 5 (moteur fiscal complet).
 *
 * Couvre les branches principales : barèmes WLTP/NEDC/PA, exonérations
 * LCD/électrique/handicap, fallback méthode CO₂, validation des entrées,
 * et structure du DTO retourné.
 *
 * Les valeurs attendues sont calculées à partir des barèmes 2024 testés
 * dans `BracketsCatalog2024Test`. Tout changement de barème casse aussi
 * ce test — c'est voulu.
 */
final class FiscalCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->app->make(FiscalCalculator::class);
    }

    #[Test]
    public function vp_wltp_100_g_120_jours_calcule_le_bon_montant(): void
    {
        // Tarif WLTP plein 100 g/km = 0+41+16+96+20 = 173 €
        // Polluants cat 1 = 100 €
        // Prorata 120/366 → CO₂ 56,72 € + polluants 32,79 € = 89,51 €
        $vehicle = $this->makeVehicleWltp(co2: 100);

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 120), [], 2024);

        $this->assertSame(HomologationMethod::Wltp, $r->co2Method);
        $this->assertSame(173.0, $r->co2FullYearTariff);
        $this->assertSame(56.72, $r->co2Due);
        $this->assertSame(100.0, $r->pollutantsFullYearTariff);
        $this->assertSame(32.79, $r->pollutantsDue);
        $this->assertSame(89.51, $r->totalDue);
        $this->assertFalse($r->lcdExempt);
        $this->assertFalse($r->electricExempt);
        $this->assertFalse($r->handicapExempt);
        $this->assertSame([], $r->appliedExemptions);
    }

    #[Test]
    public function vp_nedc_130_g_250_jours_calcule_le_bon_montant(): void
    {
        // Tarif NEDC plein 130 g/km = 33+14+81+64+170+800+120 = 1 282 €
        $vehicle = $this->makeVehicleNedc(co2: 130);

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 250), [], 2024);

        $this->assertSame(HomologationMethod::Nedc, $r->co2Method);
        $this->assertSame(1282.0, $r->co2FullYearTariff);
        $this->assertSame(875.68, $r->co2Due);
        $this->assertSame(68.31, $r->pollutantsDue);
        $this->assertSame(943.99, $r->totalDue);
    }

    #[Test]
    public function vu_pa_7_cv_pleine_annee_calcule_le_bon_montant(): void
    {
        // Tarif PA plein 7 CV = 4500+6750+3750 = 15 000 €
        // Polluants "plus polluants" = 500 €
        $vehicle = $this->makeVehiclePa(cv: 7, pollutant: PollutantCategory::MostPolluting);

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 366), [], 2024);

        $this->assertSame(HomologationMethod::Pa, $r->co2Method);
        $this->assertSame(15000.0, $r->co2FullYearTariff);
        $this->assertSame(15000.0, $r->co2Due);
        $this->assertSame(500.0, $r->pollutantsDue);
        $this->assertSame(15500.0, $r->totalDue);
    }

    #[Test]
    public function lcd_30_jours_donne_exoneration_totale(): void
    {
        $vehicle = $this->makeVehicleWltp(co2: 100);

        $r = $this->calculator->calculate($vehicle, $this->lcdContractsForDays($vehicle, 30), [], 2024);

        $this->assertTrue($r->lcdExempt);
        $this->assertSame(0.0, $r->co2Due);
        $this->assertSame(0.0, $r->pollutantsDue);
        $this->assertSame(0.0, $r->totalDue);
        $this->assertCount(1, $r->appliedExemptions);
        $this->assertStringContainsString('LCD', $r->appliedExemptions[0]->reason);
        $this->assertStringContainsString('30 j', $r->appliedExemptions[0]->reason);
        $this->assertSame('R-2024-021', $r->appliedExemptions[0]->ruleCode);
    }

    #[Test]
    public function lcd_31_jours_ne_donne_pas_exoneration(): void
    {
        $vehicle = $this->makeVehicleWltp(co2: 100);

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 31), [], 2024);

        $this->assertFalse($r->lcdExempt);
        // 173 × 31/366 = 14,6530... → 14,65 ; 100 × 31/366 = 8,4699... → 8,47
        $this->assertSame(14.65, $r->co2Due);
        $this->assertSame(8.47, $r->pollutantsDue);
        $this->assertSame(23.12, $r->totalDue);
        $this->assertSame([], $r->appliedExemptions);
    }

    #[Test]
    public function vehicule_electrique_exonere_de_la_taxe_co2(): void
    {
        $vehicle = $this->makeVehicleElectric();

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 200), [], 2024);

        $this->assertTrue($r->electricExempt);
        $this->assertSame(0.0, $r->co2FullYearTariff);
        $this->assertSame(0.0, $r->co2Due);
        // Polluants cat E = 0 € (effet du barème, pas exonération)
        $this->assertSame(0.0, $r->pollutantsDue);
        $this->assertSame(0.0, $r->totalDue);
        $this->assertCount(1, $r->appliedExemptions);
        $this->assertStringContainsString('électrique', $r->appliedExemptions[0]->reason);
        $this->assertSame('R-2024-016', $r->appliedExemptions[0]->ruleCode);
    }

    #[Test]
    public function vehicule_handicap_court_circuite_tout_le_calcul(): void
    {
        // Même un véhicule "plus polluant" Diesel WLTP haut CO₂ doit
        // être totalement exonéré si handicap_access = true.
        $vehicle = $this->makeVehicleWltp(
            co2: 200,
            energy: EnergySource::Diesel,
            pollutant: PollutantCategory::MostPolluting,
            handicapAccess: true,
        );

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 300), [], 2024);

        $this->assertTrue($r->handicapExempt);
        $this->assertFalse($r->lcdExempt);
        $this->assertFalse($r->electricExempt);
        $this->assertSame(0.0, $r->co2Due);
        $this->assertSame(0.0, $r->pollutantsDue);
        $this->assertSame(0.0, $r->totalDue);
        $this->assertCount(1, $r->appliedExemptions);
        $this->assertStringContainsString('handicap', $r->appliedExemptions[0]->reason);
        $this->assertSame('R-2024-015', $r->appliedExemptions[0]->ruleCode);
    }

    #[Test]
    public function methode_pa_sur_vehicule_ancien_sans_co2(): void
    {
        // Cas réaliste : véhicule pré-2004 ou import sans donnée CO₂,
        // déclaré directement PA dès la création (R-2024-005). La garde
        // DB `chk_vfc_homologation_implies_measurement` empêche WLTP/NEDC
        // sans la mesure correspondante — bascule applicative vers PA.
        $vehicle = $this->makeVehiclePa(cv: 5, pollutant: PollutantCategory::Category1);

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 100), [], 2024);

        $this->assertSame(HomologationMethod::Pa, $r->co2Method);
        // 5 CV → 1500*3 + 2250*2 = 4500 + 4500 = 9000 €
        $this->assertSame(9000.0, $r->co2FullYearTariff);
        // 9000 × 100/366 = 2459,0163… → 2459,02
        $this->assertSame(2459.02, $r->co2Due);
    }

    #[Test]
    public function annee_non_supportee_leve_fiscal_calculation_exception(): void
    {
        $vehicle = $this->makeVehicleWltp(co2: 100);

        $this->expectException(FiscalCalculationException::class);
        $this->expectExceptionMessage('not supported');

        $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 100), [], 2099);
    }

    // Tests obsolètes retirés (refonte 04.F) :
    // - `jours_negatifs_levent_fiscal_calculation_exception` : la
    //   nouvelle signature ne reçoit plus d'entier de jours, donc la
    //   validation amont disparaît (R-2024-002 calcule le numérateur
    //   depuis les contrats taxables, garanti ≥ 0 par construction).
    // - `cumul_inferieur_aux_jours_attribues_leve_fiscal_calculation_exception` :
    //   la sémantique cumul/jours est révoquée par ADR-0014.

    #[Test]
    public function vehicule_sans_caracteristiques_courantes_leve_fiscal_calculation_exception(): void
    {
        $vehicle = Vehicle::create([
            'license_plate' => 'XX-999-XX',
            'brand' => 'Test',
            'model' => 'NoFiscal',
            'first_french_registration_date' => Carbon::parse('2022-01-01'),
            'first_origin_registration_date' => Carbon::parse('2022-01-01'),
            'first_economic_use_date' => Carbon::parse('2022-01-01'),
            'acquisition_date' => Carbon::parse('2022-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);

        $this->expectException(FiscalCalculationException::class);
        $this->expectExceptionMessage('no current fiscal characteristics');

        $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 100), [], 2024);
    }

    #[Test]
    public function from_breakdown_mapping_complet_vers_dto_spatie(): void
    {
        $vehicle = $this->makeVehicleWltp(co2: 100);

        $breakdown = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 50), [], 2024);
        $data = FiscalBreakdownData::fromBreakdown($breakdown);

        // Conversion 1:1 vérifiée champ par champ
        $this->assertSame($breakdown->daysAssigned, $data->daysAssigned);
        $this->assertSame($breakdown->cumulativeDaysForPair, $data->cumulativeDaysForPair);
        $this->assertSame($breakdown->daysInYear, $data->daysInYear);
        $this->assertSame($breakdown->lcdExempt, $data->lcdExempt);
        $this->assertSame($breakdown->electricExempt, $data->electricExempt);
        $this->assertSame($breakdown->handicapExempt, $data->handicapExempt);
        $this->assertSame($breakdown->co2Method, $data->co2Method);
        $this->assertSame($breakdown->co2FullYearTariff, $data->co2FullYearTariff);
        $this->assertSame($breakdown->co2Due, $data->co2Due);
        $this->assertSame($breakdown->pollutantCategory, $data->pollutantCategory);
        $this->assertSame($breakdown->pollutantsFullYearTariff, $data->pollutantsFullYearTariff);
        $this->assertSame($breakdown->pollutantsDue, $data->pollutantsDue);
        $this->assertSame($breakdown->totalDue, $data->totalDue);
        // appliedExemptions est mappé via fromValueObject ; on compare par
        // longueur (le test couvre un cas sans exonération → liste vide).
        $this->assertCount(count($breakdown->appliedExemptions), $data->appliedExemptions);

        // Sérialisation Spatie expose les bons noms (camelCase)
        $payload = $data->toArray();
        $this->assertArrayHasKey('daysAssigned', $payload);
        $this->assertArrayHasKey('co2Method', $payload);
        $this->assertSame('WLTP', $payload['co2Method']);
    }

    #[Test]
    public function days_in_year_2024_vaut_366_jours_bissextiles(): void
    {
        $vehicle = $this->makeVehicleWltp(co2: 100);

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 100), [], 2024);

        $this->assertSame(366, $r->daysInYear);
    }

    #[Test]
    public function vehicule_m1_corbillard_n_est_pas_taxable_r004(): void
    {
        $vehicle = $this->makeBaseVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Diesel,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::MostPolluting,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 200,
            'taxable_horsepower' => 8,
            'handicap_access' => false,
            'm1_special_use' => true, // ← R-2024-004 : usage spécial → hors champ
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);
        $vehicle = $vehicle->fresh();

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 200), [], 2024);

        // R-2024-004 court-circuite : tous les montants à zéro
        $this->assertSame(0.0, $r->co2FullYearTariff);
        $this->assertSame(0.0, $r->co2Due);
        $this->assertSame(0.0, $r->pollutantsDue);
        $this->assertSame(0.0, $r->totalDue);
    }

    #[Test]
    public function diesel_euro6_est_categorise_most_polluting_r013(): void
    {
        // R-2024-013 : Diesel Euro 6 → Most polluting (la motorisation
        // Diesel n'est pas allumage commandé, donc pas catégorie 1)
        $vehicle = $this->makeVehicleWltp(
            co2: 100,
            energy: EnergySource::Diesel,
            pollutant: PollutantCategory::Category1, // ← stocké à tort, doit être ignoré
        );

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 366), [], 2024);

        // R-013 doit déterminer Most polluting depuis les enums, pas
        // lire la valeur stockée à tort.
        $this->assertSame(PollutantCategory::MostPolluting, $r->pollutantCategory);
        $this->assertSame(500.0, $r->pollutantsFullYearTariff);
    }

    #[Test]
    public function hybride_essence_recent_120g_est_exonere_co2_r017(): void
    {
        // Hybride essence + électrique, ancienneté < 3 ans au 01/01/2024,
        // CO₂ WLTP 120 g/km → régime aménagé seuil 120 → exonéré CO₂
        $vehicle = $this->makeBaseVehicle();
        $vehicle->first_origin_registration_date = Carbon::parse('2022-06-01');
        $vehicle->save();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::PluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'euro_standard' => EuroStandard::Euro6dIscFcm,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 120,
            'taxable_horsepower' => 7,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);
        $vehicle = $vehicle->fresh();

        $r = $this->calculator->calculate($vehicle, $this->contractsForDays($vehicle, 366), [], 2024);

        $this->assertTrue($r->electricExempt); // mécanique scope=Co2Only
        $this->assertSame(0.0, $r->co2FullYearTariff);
        $this->assertSame(0.0, $r->co2Due);
        // Polluants Category 1 toujours dus (R-017 = Co2Only)
        $this->assertSame(100.0, $r->pollutantsFullYearTariff);
        $this->assertSame(100.0, $r->pollutantsDue);
        $this->assertCount(1, $r->appliedExemptions);
        $this->assertStringContainsString('hybride', $r->appliedExemptions[0]->reason);
        $this->assertSame('R-2024-017', $r->appliedExemptions[0]->ruleCode);
    }

    #[Test]
    public function fiscal_breakdown_est_immuable_readonly(): void
    {
        $r = new FiscalBreakdown(
            daysAssigned: 100,
            cumulativeDaysForPair: 100,
            daysInYear: 366,
            lcdExempt: false,
            electricExempt: false,
            handicapExempt: false,
            co2Method: HomologationMethod::Wltp,
            co2FullYearTariff: 173.0,
            co2Due: 47.27,
            pollutantCategory: PollutantCategory::Category1,
            pollutantsFullYearTariff: 100.0,
            pollutantsDue: 27.32,
            totalDue: 74.59,
            appliedExemptions: [],
        );

        // Toutes les propriétés sont readonly — on vérifie via reflection.
        $reflection = new \ReflectionClass($r);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Propriété {$property->getName()} devrait être readonly",
            );
        }
    }

    // ─── Helpers de fabrication ──────────────────────────────────────

    private static int $plateCounter = 0;

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('TT-%03d-TT', $n);
    }

    /**
     * Construit un contrat synthétique non-persisté de `$days` jours
     * pour le couple `(vehicle, companyId=0)`. Choisit une plage de
     * dates qui évite le cas-limite « mois civil entier » pour `$days`
     * compris entre 28 et 31 (sauf si on veut explicitement tester un
     * contrat ≤ 30 j → toujours LCD).
     *
     * @return list<Contract>
     */
    private function contractsForDays(Vehicle $vehicle, int $days): array
    {
        // Démarrage en milieu de mois pour les tailles à risque
        // (29-31 jours pourrait sinon coïncider avec un mois civil
        // entier et rendre le contrat LCD à tort).
        $start = ($days >= 29 && $days <= 31) ? '2024-01-15' : '2024-01-01';
        $end = (new \DateTimeImmutable($start))
            ->modify('+'.($days - 1).' days')
            ->format('Y-m-d');

        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'driver_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return [$contract];
    }

    /**
     * Variante qui force la qualification LCD (durée ≤ 30 j garantie).
     *
     * @return list<Contract>
     */
    private function lcdContractsForDays(Vehicle $vehicle, int $days): array
    {
        $start = '2024-01-01';
        $end = (new \DateTimeImmutable($start))
            ->modify('+'.($days - 1).' days')
            ->format('Y-m-d');

        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'driver_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lcd->value,
            'notes' => null,
        ], true);

        return [$contract];
    }

    private function makeVehicleWltp(
        int $co2,
        EnergySource $energy = EnergySource::Gasoline,
        PollutantCategory $pollutant = PollutantCategory::Category1,
        bool $handicapAccess = false,
    ): Vehicle {
        $vehicle = $this->makeBaseVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => $handicapAccess ? BodyType::Handicap : BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => $energy,
            'euro_standard' => EuroStandard::Euro6d,
            'pollutant_category' => $pollutant,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'taxable_horsepower' => 6,
            'handicap_access' => $handicapAccess,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleNedc(int $co2): Vehicle
    {
        $vehicle = $this->makeBaseVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro5,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Nedc,
            'co2_nedc' => $co2,
            'taxable_horsepower' => 5,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeVehiclePa(int $cv, PollutantCategory $pollutant): Vehicle
    {
        $vehicle = $this->makeBaseVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::LightTruck,
            'seats_count' => 5,
            'energy_source' => EnergySource::Diesel,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => $pollutant,
            'homologation_method' => HomologationMethod::Pa,
            'taxable_horsepower' => $cv,
            'handicap_access' => false,
            // R-2024-004 : N1 LightTruck taxable seulement si banquette
            // amovible 2 rangs ET affectation transport personnes
            'n1_removable_second_row_seat' => true,
            'n1_passenger_transport' => true,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleElectric(): Vehicle
    {
        $vehicle = $this->makeBaseVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Electric,
            'pollutant_category' => PollutantCategory::E,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 0,
            'taxable_horsepower' => 9,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function makeBaseVehicle(): Vehicle
    {
        $plate = $this->nextPlate();

        return Vehicle::create([
            'license_plate' => $plate,
            'brand' => 'TestBrand',
            'model' => 'TestModel',
            'first_french_registration_date' => Carbon::parse('2022-06-15'),
            'first_origin_registration_date' => Carbon::parse('2022-06-15'),
            'first_economic_use_date' => Carbon::parse('2022-06-15'),
            'acquisition_date' => Carbon::parse('2022-06-15'),
            'current_status' => VehicleStatus::Active,
        ]);
    }
}
