<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests isolés de l'orchestration création véhicule + caractéristiques
 * fiscales initiales. Couvre la transaction atomique et le rollback.
 */
final class CreateVehicleActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateVehicleAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(CreateVehicleAction::class);
    }

    #[Test]
    public function cree_le_vehicule_et_la_premiere_periode_fiscale_atomiquement(): void
    {
        $data = $this->makeData();

        $vehicle = $this->action->execute($data);

        $this->assertInstanceOf(Vehicle::class, $vehicle);
        $this->assertSame('AB-456-CD', $vehicle->license_plate);

        $this->assertDatabaseHas('vehicles', ['license_plate' => 'AB-456-CD']);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'co2_wltp' => 110,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation->value,
        ]);
    }

    #[Test]
    public function aligne_effective_from_sur_la_date_d_acquisition(): void
    {
        $data = $this->makeData(acquisitionDate: '2023-06-15');

        $vehicle = $this->action->execute($data);

        $vfc = VehicleFiscalCharacteristics::where('vehicle_id', $vehicle->id)->firstOrFail();
        $this->assertSame('2023-06-15', $vfc->effective_from->toDateString());
        $this->assertNull($vfc->effective_to);
    }

    #[Test]
    public function rollback_si_la_creation_de_la_periode_fiscale_echoue(): void
    {
        $vfcRepo = $this->createMock(VehicleFiscalCharacteristicsWriteRepositoryInterface::class);
        $vfcRepo
            ->expects($this->once())
            ->method('createInitialVersion')
            ->willThrowException(new \RuntimeException('boom'));

        $this->app->instance(VehicleFiscalCharacteristicsWriteRepositoryInterface::class, $vfcRepo);
        $action = $this->app->make(CreateVehicleAction::class);

        try {
            $action->execute($this->makeData(licensePlate: 'XX-000-XX'));
            $this->fail('Exception attendue.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        // Le véhicule a été créé dans la transaction puis rollback -
        // aucune ligne ne doit subsister.
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'XX-000-XX']);
        $this->assertSame(0, DB::table('vehicles')->count());
    }

    private function makeData(
        string $licensePlate = 'AB-456-CD',
        string $acquisitionDate = '2024-01-10',
    ): StoreVehicleData {
        return new StoreVehicleData(
            licensePlate: $licensePlate,
            brand: 'Renault',
            model: 'Megane',
            vin: null,
            color: null,
            firstFrenchRegistrationDate: '2024-01-01',
            firstOriginRegistrationDate: '2024-01-01',
            firstEconomicUseDate: '2024-01-05',
            acquisitionDate: $acquisitionDate,
            mileageCurrent: 0,
            notes: null,
            receptionCategory: ReceptionCategory::M1,
            vehicleUserType: VehicleUserType::PassengerCar,
            bodyType: BodyType::InteriorDriving,
            seatsCount: 5,
            energySource: EnergySource::Gasoline,
            underlyingCombustionEngineType: null,
            euroStandard: EuroStandard::Euro6,
            homologationMethod: HomologationMethod::Wltp,
            co2Wltp: 110,
            co2Nedc: null,
            taxableHorsepower: 6,
        );
    }
}
