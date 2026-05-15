<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Exemption;

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
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Exemption\R2024_017_ConditionalHybridExemption;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Φ.1 · couverture exhaustive du régime aménagé R-2024-017
 * (CIBS L. 421-125 v 2024).
 *
 * **Régime aménagé** · véhicule < 3 ans au 01/01/2024 · seuils
 * doublés ·
 *   - WLTP ≤ 120 g/km
 *   - NEDC ≤ 100 g/km
 *   - PA ≤ 6 CV
 *
 * Les tests existants (R2024_017_ConditionalHybridExemptionTest)
 * couvrent WLTP + PA général + WLTP aménagé. Ce fichier complète ·
 *   - NEDC général et aménagé (manquant)
 *   - PA aménagé (manquant)
 *   - Bornes inclusives et exclusives de l'âge 3 ans
 */
final class R2024_017_AdjustedRegimeTest extends TestCase
{
    use RefreshDatabase;

    private R2024_017_ConditionalHybridExemption $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2024_017_ConditionalHybridExemption;
    }

    #[Test]
    public function hybride_essence_nedc_50_3_ans_donne_exoneration_general(): void
    {
        // ≥ 3 ans au 01/01/2024 · NEDC ≤ 50 (seuil général)
        $vehicle = $this->makeVehicle('2020-06-15');
        $vfc = $this->makeVfcNedc($vehicle, 50);

        $verdict = $this->rule->evaluate($this->makeContext($vehicle, $vfc));

        self::assertTrue($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_nedc_51_3_ans_depasse_seuil_general(): void
    {
        $vehicle = $this->makeVehicle('2020-06-15');
        $vfc = $this->makeVfcNedc($vehicle, 51);

        $verdict = $this->rule->evaluate($this->makeContext($vehicle, $vfc));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_nedc_100_recent_donne_exoneration_amenage(): void
    {
        // < 3 ans au 01/01/2024 · NEDC ≤ 100 (seuil aménagé)
        $vehicle = $this->makeVehicle('2023-06-15');
        $vfc = $this->makeVfcNedc($vehicle, 100);

        $verdict = $this->rule->evaluate($this->makeContext($vehicle, $vfc));

        self::assertTrue($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_nedc_101_recent_depasse_seuil_amenage(): void
    {
        $vehicle = $this->makeVehicle('2023-06-15');
        $vfc = $this->makeVfcNedc($vehicle, 101);

        $verdict = $this->rule->evaluate($this->makeContext($vehicle, $vfc));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_pa_6cv_recent_donne_exoneration_amenage(): void
    {
        // < 3 ans · PA ≤ 6 (seuil aménagé)
        $vehicle = $this->makeVehicle('2023-06-15');
        $vfc = $this->makeVfcPa($vehicle, 6);

        $verdict = $this->rule->evaluate($this->makeContext($vehicle, $vfc));

        self::assertTrue($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_pa_7cv_recent_depasse_seuil_amenage(): void
    {
        $vehicle = $this->makeVehicle('2023-06-15');
        $vfc = $this->makeVfcPa($vehicle, 7);

        $verdict = $this->rule->evaluate($this->makeContext($vehicle, $vfc));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function age_exactement_3_ans_au_01_01_2024_est_traite_comme_general(): void
    {
        // Véhicule immatriculé EXACTEMENT le 01/01/2021 · age = 3 ans
        // au 01/01/2024 · est-il traité comme >= 3 ans (général) ?
        // diffInYears(01/01/2024, 01/01/2021) = 3 exactement.
        // `$isAdjustedRegime = $vehicleAgeYears < AGE_THRESHOLD_YEARS (3)`
        // → 3 < 3 = false · général. WLTP 121 doit échouer général
        // (seuil 60) mais aurait passé en aménagé (seuil 120).
        $vehicle = $this->makeVehicle('2021-01-01');
        $vfc = $this->makeVfcWltp($vehicle, 100); // > 60 général, < 120 aménagé

        $verdict = $this->rule->evaluate($this->makeContext($vehicle, $vfc));

        // Doit échouer en régime général
        self::assertFalse($verdict->isExempt, 'Âge exactement 3 ans = régime général · WLTP 100 > 60 doit échouer.');
    }

    #[Test]
    public function age_2_ans_364_jours_au_01_01_2024_est_aménage(): void
    {
        // Véhicule immat 02/01/2021 · age = 2 ans 364 jours au 01/01/2024
        // → < 3 ans · régime aménagé.
        $vehicle = $this->makeVehicle('2021-01-02');
        $vfc = $this->makeVfcWltp($vehicle, 100); // OK aménagé (≤ 120)

        $verdict = $this->rule->evaluate($this->makeContext($vehicle, $vfc));

        self::assertTrue($verdict->isExempt, 'Âge 2 ans 364 jours · régime aménagé · WLTP 100 ≤ 120 doit passer.');
    }

    private function makeVehicle(string $firstOriginRegDate): Vehicle
    {
        return Vehicle::create([
            'license_plate' => sprintf('Z%d-%03d-Z%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
            'brand' => 'Toyota',
            'model' => 'Hybrid Φ.1',
            'first_french_registration_date' => Carbon::parse($firstOriginRegDate),
            'first_origin_registration_date' => Carbon::parse($firstOriginRegDate),
            'first_economic_use_date' => Carbon::parse($firstOriginRegDate),
            'acquisition_date' => Carbon::parse($firstOriginRegDate),
            'current_status' => VehicleStatus::Active,
        ]);
    }

    private function makeVfcNedc(Vehicle $vehicle, int $co2): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $vehicle->first_origin_registration_date,
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::NonPluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => $co2,
            'taxable_horsepower' => 5,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);
    }

    private function makeVfcWltp(Vehicle $vehicle, int $co2): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $vehicle->first_origin_registration_date,
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::NonPluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'euro_standard' => EuroStandard::Euro6d,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'co2_nedc' => null,
            'taxable_horsepower' => 5,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);
    }

    private function makeVfcPa(Vehicle $vehicle, int $cv): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $vehicle->first_origin_registration_date,
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::NonPluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'euro_standard' => EuroStandard::Euro4,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => $cv,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);
    }

    private function makeContext(Vehicle $vehicle, VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
