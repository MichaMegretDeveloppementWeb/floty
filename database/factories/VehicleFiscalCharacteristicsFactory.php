<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a passenger car, gasoline, Euro 6d, WLTP, category 1.
 *
 * @extends Factory<VehicleFiscalCharacteristics>
 */
final class VehicleFiscalCharacteristicsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            // Wide effective window so the segmented fiscal orchestrator finds a
            // matching VFC for any post-2020 contract created by tests.
            'effective_from' => Carbon::create(2020, 1, 1),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::StationWagon,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'underlying_combustion_engine_type' => null,
            'euro_standard' => EuroStandard::Euro6dIscFcm,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 120,
            'co2_nedc' => null,
            'accepts_e85' => false,
            'taxable_horsepower' => null,
            'kerb_mass' => 1_300,
            'handicap_access' => false,
            'n1_passenger_transport' => false,
            'n1_removable_second_row_seat' => false,
            'm1_special_use' => false,
            'n1_ski_lift_use' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
            'change_note' => null,
        ];
    }

    public function electric(): static
    {
        return $this->state(fn (): array => [
            'energy_source' => EnergySource::Electric,
            'pollutant_category' => PollutantCategory::E,
            'co2_wltp' => 0,
        ]);
    }

    public function nedc(int $co2 = 130): static
    {
        return $this->state(fn (): array => [
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => $co2,
        ]);
    }

    public function pa(int $cv = 7): static
    {
        return $this->state(fn (): array => [
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => $cv,
        ]);
    }

    /** Enables the E85 flag (R-2025-023 abatement). */
    public function acceptsE85(): static
    {
        return $this->state(fn (): array => [
            'accepts_e85' => true,
        ]);
    }
}
