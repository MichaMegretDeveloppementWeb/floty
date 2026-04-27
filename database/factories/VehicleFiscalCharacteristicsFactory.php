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
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Caractéristiques fiscales d'un véhicule. Par défaut : VP WLTP
 * essence Euro 6 cat 1 (cas standard du seeder démo).
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
            'effective_from' => now()->subYear()->startOfYear(),
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
}
