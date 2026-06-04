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
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garantit qu'un contrat **traversant deux exercices fiscaux** est
 * prorata-isé indépendamment sur chaque année · aucun jour n'est compté
 * deux fois, aucun jour de l'autre année ne contamine le calcul.
 *
 * Cas étudié : contrat du 2024-12-15 au 2025-02-28 (76 jours bruts).
 * - **Pour l'année 2024** : seuls les jours 2024-12-15 → 2024-12-31
 *   sont retenus, soit **17 jours** sur 366.
 * - **Pour l'année 2025** : seuls les jours 2025-01-01 → 2025-02-28
 *   sont retenus, soit **59 jours** sur 365.
 *
 * Helper testé : `Contract::expandToDaysInYear()` qui borne le contrat
 * à l'année cible avant expansion. Toute régression qui ferait fuiter
 * des jours de l'autre année (mauvais clamping de la borne supérieure
 * ou inférieure) ferait échouer ce test · et propagerait silencieusement
 * une erreur fiscale d'une dizaine de pourcents sur la déclaration.
 *
 * Cf. audit produit 2026-05-04 § C2.c (M19) · couverture identifiée
 * manquante.
 */
final class MultiYearProrataTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->app->make(FiscalCalculator::class);
    }

    #[Test]
    public function expand_to_days_in_year_borne_un_contrat_cross_year_a_2024(): void
    {
        $contract = $this->buildCrossYearContract();

        $days = $contract->expandToDaysInYear(2024);

        // 17 jours du 2024-12-15 au 2024-12-31 inclus
        self::assertCount(17, $days);
        self::assertSame('2024-12-15', $days[0]);
        self::assertSame('2024-12-31', $days[16]);
    }

    #[Test]
    public function expand_to_days_in_year_borne_un_contrat_cross_year_a_2025(): void
    {
        $contract = $this->buildCrossYearContract();

        $days = $contract->expandToDaysInYear(2025);

        // 59 jours du 2025-01-01 au 2025-02-28 inclus (2025 non bissextile)
        self::assertCount(59, $days);
        self::assertSame('2025-01-01', $days[0]);
        self::assertSame('2025-02-28', $days[58]);
    }

    #[Test]
    public function expand_to_days_in_year_renvoie_vide_pour_une_annee_hors_periode(): void
    {
        $contract = $this->buildCrossYearContract();

        // 2023 et 2026 : aucune intersection avec 2024-12-15 → 2025-02-28
        self::assertSame([], $contract->expandToDaysInYear(2023));
        self::assertSame([], $contract->expandToDaysInYear(2026));
    }

    #[Test]
    public function pipeline_2024_calcule_le_prorata_uniquement_sur_les_17_jours_de_decembre(): void
    {
        // Véhicule WLTP 100 g/km :
        // - tarif CO₂ plein 173 € → 17/366 jours = 8,03... €
        // - polluants Cat 1 100 € → 17/366 jours = 4,64... €
        // Aucun débordement sur janvier-février 2025 ne doit polluer
        // ce calcul.
        $vehicle = $this->makeVehicleWltp(co2: 100);
        $contract = $this->persistCrossYearContract($vehicle);

        $result = $this->calculator->calculate(
            vehicle: $vehicle,
            contractsForPair: [$contract],
            vehicleEvents: [],
            fiscalYear: 2024,
        );

        self::assertSame(17, $result->daysAssigned, 'Le pipeline doit retenir 17 jours en 2024 (15→31 déc).');
        self::assertSame(366, $result->daysInYear);
        self::assertSame(173.0, $result->co2FullYearTariff);
        self::assertSame(round(173.0 * 17 / 366, 2, PHP_ROUND_HALF_UP), $result->co2Due);
        self::assertSame(round(100.0 * 17 / 366, 2, PHP_ROUND_HALF_UP), $result->pollutantsDue);
    }

    #[Test]
    public function pipeline_2025_applique_le_bareme_durci_sur_les_59_jours_de_janvier_fevrier(): void
    {
        // Bloc 4 Phase L · garde-fou anti-contamination cross-année.
        // Même contrat, même véhicule WLTP 100 g/km :
        // - 2024 utilise barème WLTP 2024 = 173 € (testé ci-dessus)
        // - 2025 doit utiliser barème WLTP 2025 (durcissement LF 2024
        //   art. 97, 19°) = 193 € (vérifié goldens R2025_PricingScalesTest)
        // - Dénominateur = 365 (2025 non bissextile, vs 366 en 2024)
        // - 59 jours retenus (2025-01-01 → 2025-02-28)
        $vehicle = $this->makeVehicleWltp(co2: 100);
        $contract = $this->persistCrossYearContract($vehicle);

        $result = $this->calculator->calculate(
            vehicle: $vehicle,
            contractsForPair: [$contract],
            vehicleEvents: [],
            fiscalYear: 2025,
        );

        self::assertSame(59, $result->daysAssigned, 'Le pipeline doit retenir 59 jours en 2025 (01/01 → 28/02).');
        self::assertSame(365, $result->daysInYear, 'Dénominateur 365 pour 2025 non bissextile.');
        self::assertSame(
            193.0,
            $result->co2FullYearTariff,
            'Barème WLTP 2025 durci · 100 g/km = 193 € (vs 173 € en 2024).',
        );
        self::assertSame(round(193.0 * 59 / 365, 2, PHP_ROUND_HALF_UP), $result->co2Due);
        self::assertSame(round(100.0 * 59 / 365, 2, PHP_ROUND_HALF_UP), $result->pollutantsDue);
    }

    #[Test]
    public function pipeline_2024_ignore_le_flag_e85_meme_si_present_sur_la_vfc(): void
    {
        // Bloc 4 Phase L · l'abattement E85 (R-2025-023) est exclusif
        // au pipeline 2025. Un véhicule flex-fuel utilisé en 2024 doit
        // payer le plein tarif 2024 sans réduction.
        $vehicle = $this->makeVehicleWltp(co2: 100, acceptsE85: true);
        $contract = $this->persistCrossYearContract($vehicle);

        $result = $this->calculator->calculate(
            vehicle: $vehicle,
            contractsForPair: [$contract],
            vehicleEvents: [],
            fiscalYear: 2024,
        );

        // Tarif plein 2024 sans abattement · 173 € (vs 117 € avec
        // abattement 40 % en 2025).
        self::assertSame(173.0, $result->co2FullYearTariff);
    }

    #[Test]
    public function pipeline_2025_applique_abattement_e85_sur_le_meme_vehicule(): void
    {
        // Même véhicule WLTP 100 g/km flex-fuel · en 2025 l'abattement
        // E85 (R-2025-023) réduit le CO₂ à 60 g/km avant tarification.
        // Barème 2025 sur 60 g/km = (50-9)*1 + (58-50)*2 + (60-58)*3
        //   = 41 + 16 + 6 = 63 € (vs 193 € sans abattement).
        $vehicle = $this->makeVehicleWltp(co2: 100, acceptsE85: true);
        $contract = $this->persistCrossYearContract($vehicle);

        $result = $this->calculator->calculate(
            vehicle: $vehicle,
            contractsForPair: [$contract],
            vehicleEvents: [],
            fiscalYear: 2025,
        );

        self::assertSame(63.0, $result->co2FullYearTariff);
    }

    private function buildCrossYearContract(): Contract
    {
        // Contrat sans persistance (pas besoin de respecter les triggers
        // d'overlap · on teste juste l'expansion mathématique).
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => 1,
            'company_id' => 1,
            'start_date' => '2024-12-15',
            'end_date' => '2025-02-28',
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private function persistCrossYearContract(Vehicle $vehicle): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'start_date' => '2024-12-15',
            'end_date' => '2025-02-28',
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private function makeVehicleWltp(int $co2, bool $acceptsE85 = false): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => 'MY-001-MY',
            'brand' => 'TestBrand',
            'model' => 'TestModel',
            'first_french_registration_date' => Carbon::parse('2022-06-15'),
            'first_origin_registration_date' => Carbon::parse('2022-06-15'),
            'first_economic_use_date' => Carbon::parse('2022-06-15'),
            'acquisition_date' => Carbon::parse('2022-06-15'),
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
            'euro_standard' => EuroStandard::Euro6d,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'taxable_horsepower' => 6,
            'accepts_e85' => $acceptsE85,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }
}
