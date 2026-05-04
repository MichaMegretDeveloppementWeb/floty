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
 * prorata-isé indépendamment sur chaque année — aucun jour n'est compté
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
 * ou inférieure) ferait échouer ce test — et propagerait silencieusement
 * une erreur fiscale d'une dizaine de pourcents sur la déclaration.
 *
 * Cf. audit produit 2026-05-04 § C2.c (M19) — couverture identifiée
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
            vehicleUnavailabilities: [],
            fiscalYear: 2024,
        );

        self::assertSame(17, $result->daysAssigned, 'Le pipeline doit retenir 17 jours en 2024 (15→31 déc).');
        self::assertSame(366, $result->daysInYear);
        self::assertSame(173.0, $result->co2FullYearTariff);
        self::assertSame(round(173.0 * 17 / 366, 2, PHP_ROUND_HALF_UP), $result->co2Due);
        self::assertSame(round(100.0 * 17 / 366, 2, PHP_ROUND_HALF_UP), $result->pollutantsDue);
    }

    private function buildCrossYearContract(): Contract
    {
        // Contrat sans persistance (pas besoin de respecter les triggers
        // d'overlap — on teste juste l'expansion mathématique).
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => 1,
            'company_id' => 1,
            'driver_id' => null,
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
            'driver_id' => null,
            'start_date' => '2024-12-15',
            'end_date' => '2025-02-28',
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private function makeVehicleWltp(int $co2): Vehicle
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
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }
}
