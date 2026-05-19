<?php

declare(strict_types=1);

namespace Tests\Unit\Managers\VehicleRegistryLookup\Drivers;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;
use App\Managers\VehicleRegistryLookup\Drivers\FakeVehicleRegistryLookupDriver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FakeVehicleRegistryLookupDriverTest extends TestCase
{
    #[Test]
    public function expose_le_driver_fake(): void
    {
        $driver = $this->makeDriver();

        $this->assertSame(RegistryLookupDriver::Fake, $driver->driverName());
    }

    #[Test]
    public function renvoie_les_donnees_pour_une_plaque_de_fixture_par_defaut(): void
    {
        $driver = $this->makeDriver();

        $result = $driver->lookup('AA-123-AA');

        $this->assertSame('AA123AA', $result->licensePlate);
        $this->assertSame('Peugeot', $result->brand);
        $this->assertSame('308', $result->model);
        $this->assertSame(ReceptionCategory::M1, $result->receptionCategory);
        $this->assertSame(EnergySource::Gasoline, $result->energySource);
        $this->assertSame(RegistryLookupDriver::Fake, $result->sourceDriver);
        $this->assertNotEmpty($result->fetchedAt);
    }

    #[Test]
    public function normalise_la_plaque_avant_match_de_fixture(): void
    {
        $driver = $this->makeDriver();

        $resultLowercase = $driver->lookup('aa 123 aa');
        $resultUppercase = $driver->lookup('AA123AA');

        $this->assertSame($resultUppercase->brand, $resultLowercase->brand);
        $this->assertSame('AA123AA', $resultLowercase->licensePlate);
    }

    #[Test]
    public function leve_vehicle_not_found_exception_pour_plaque_inconnue(): void
    {
        $driver = $this->makeDriver();

        $this->expectException(VehicleNotFoundException::class);

        $driver->lookup('ZZ-999-ZZ');
    }

    #[Test]
    public function fixtures_de_config_surchargent_les_fixtures_par_defaut(): void
    {
        config([
            'vehicle-registry.providers.fake.fixtures' => [
                'XX111XX' => [
                    'brand' => 'TestBrand',
                    'model' => 'TestModel',
                    'receptionCategory' => 'N1',
                    'energySource' => 'electric',
                ],
            ],
        ]);

        $driver = $this->makeDriver();

        $result = $driver->lookup('XX-111-XX');

        $this->assertSame('TestBrand', $result->brand);
        $this->assertSame('TestModel', $result->model);
        $this->assertSame(ReceptionCategory::N1, $result->receptionCategory);
        $this->assertSame(EnergySource::Electric, $result->energySource);
    }

    /**
     * Resolve a driver instance through the container.
     */
    private function makeDriver(): FakeVehicleRegistryLookupDriver
    {
        return $this->app->make(FakeVehicleRegistryLookupDriver::class);
    }
}
