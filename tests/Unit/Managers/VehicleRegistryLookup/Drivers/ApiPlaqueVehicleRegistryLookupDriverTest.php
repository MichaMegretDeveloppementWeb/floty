<?php

declare(strict_types=1);

namespace Tests\Unit\Managers\VehicleRegistryLookup\Drivers;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupRateLimitedException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;
use App\Managers\VehicleRegistryLookup\Drivers\ApiPlaqueVehicleRegistryLookupDriver;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiPlaqueVehicleRegistryLookupDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vehicle-registry.providers.api_plaque' => [
                'base_url' => 'https://example-api.local',
                'host' => 'example-api.local',
                'api_key' => 'test-key',
                'timeout_seconds' => 5,
            ],
        ]);
    }

    #[Test]
    public function expose_le_driver_api_plaque(): void
    {
        $driver = $this->makeDriver();

        $this->assertSame(RegistryLookupDriver::ApiPlaque, $driver->driverName());
    }

    #[Test]
    public function mappe_correctement_une_camionnette_iveco_diesel_2009(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response($this->ivecoCamionnetteResponse(), 200),
        ]);

        $result = $this->makeDriver()->lookup('AG-371-SB');

        $this->assertSame('AG371SB', $result->licensePlate);
        $this->assertSame('Iveco', $result->brand);
        $this->assertSame('35C13', $result->model);
        $this->assertSame('ZCFC3594005808662', $result->vin);
        $this->assertSame('Vert C', $result->color);
        $this->assertSame('2009-12-07', $result->firstFrenchRegistrationDate);
        $this->assertSame(ReceptionCategory::N1, $result->receptionCategory);
        $this->assertNull(
            $result->bodyType,
            'BENNE is not a Floty body type, the mapper must leave the field empty.',
        );
        $this->assertSame(3, $result->seatsCount);
        $this->assertSame(EnergySource::Diesel, $result->energySource);
        $this->assertSame(
            EuroStandard::Euro4,
            $result->euroStandard,
            'AWN_norme_euro is INCONNU but AWN_env_class is EURO4 so the fallback must apply.',
        );
        $this->assertSame(HomologationMethod::Nedc, $result->homologationMethod);
        $this->assertSame(250, $result->co2Nedc);
        $this->assertNull($result->co2Wltp);
        $this->assertSame(8, $result->taxableHorsepower);
        $this->assertNull($result->kerbMass);
        $this->assertSame(RegistryLookupDriver::ApiPlaque, $result->sourceDriver);
    }

    #[Test]
    public function mappe_correctement_une_renault_clio_essence_2022(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response($this->renaultClioResponse(), 200),
        ]);

        $result = $this->makeDriver()->lookup('FH-034-DD');

        $this->assertSame('FH034DD', $result->licensePlate);
        $this->assertSame('Renault', $result->brand);
        $this->assertSame('Clio', $result->model);
        $this->assertSame(ReceptionCategory::M1, $result->receptionCategory);
        $this->assertSame(BodyType::InteriorDriving, $result->bodyType);
        $this->assertSame(EnergySource::Diesel, $result->energySource);
        $this->assertSame(HomologationMethod::Nedc, $result->homologationMethod);
    }

    #[Test]
    public function derive_wltp_quand_la_date_est_apres_mars_2020(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response([
                'error' => false,
                'data' => [
                    'AWN_date_mise_en_circulation_us' => '2022-05-10',
                    'AWN_emission_co_2' => '125',
                    'AWN_energie' => 'ESSENCE',
                ],
            ], 200),
        ]);

        $result = $this->makeDriver()->lookup('AB123CD');

        $this->assertSame(HomologationMethod::Wltp, $result->homologationMethod);
        $this->assertSame(125, $result->co2Wltp);
        $this->assertNull($result->co2Nedc);
    }

    #[Test]
    public function derive_pa_pour_un_vehicule_pre_2004(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response([
                'error' => false,
                'data' => [
                    'AWN_date_mise_en_circulation_us' => '2001-07-08',
                ],
            ], 200),
        ]);

        $result = $this->makeDriver()->lookup('AB123CD');

        $this->assertSame(HomologationMethod::Pa, $result->homologationMethod);
    }

    #[Test]
    public function leve_vehicle_not_found_exception_quand_l_api_renvoie_error_true(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response([
                'error' => true,
                'code' => 400,
                'message' => 'Plaque introuvable',
                'data' => [],
            ], 200),
        ]);

        $this->expectException(VehicleNotFoundException::class);

        $this->makeDriver()->lookup('ZZ999ZZ');
    }

    #[Test]
    public function leve_vehicle_not_found_quand_data_est_vide(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response([
                'error' => false,
                'data' => [],
            ], 200),
        ]);

        $this->expectException(VehicleNotFoundException::class);

        $this->makeDriver()->lookup('ZZ999ZZ');
    }

    #[Test]
    public function leve_rate_limited_exception_sur_un_429(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response([], 429, ['Retry-After' => '60']),
        ]);

        $this->expectException(RegistryLookupRateLimitedException::class);

        $this->makeDriver()->lookup('AB123CD');
    }

    #[Test]
    public function leve_unavailable_quand_la_config_est_incomplete(): void
    {
        config(['vehicle-registry.providers.api_plaque.api_key' => null]);

        $this->expectException(RegistryLookupUnavailableException::class);

        $this->makeDriver()->lookup('AB123CD');
    }

    #[Test]
    public function ignore_les_valeurs_inconnu_et_zero(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response([
                'error' => false,
                'data' => [
                    'AWN_marque' => 'INCONNU',
                    'AWN_modele' => '',
                    'AWN_VIN' => '0',
                    'AWN_couleur' => '   ',
                    'AWN_PV' => '0',
                ],
            ], 200),
        ]);

        $result = $this->makeDriver()->lookup('AB123CD');

        $this->assertNull($result->brand);
        $this->assertNull($result->model);
        $this->assertNull($result->vin);
        $this->assertNull($result->color);
        $this->assertNull($result->kerbMass);
    }

    #[Test]
    public function laisse_tous_les_booleens_fiscaux_a_null(): void
    {
        Http::fake([
            'example-api.local/*' => Http::response([
                'error' => false,
                'data' => ['AWN_marque' => 'RENAULT'],
            ], 200),
        ]);

        $result = $this->makeDriver()->lookup('AB123CD');

        $this->assertNull($result->acceptsE85);
        $this->assertNull($result->handicapAccess);
        $this->assertNull($result->m1SpecialUse);
        $this->assertNull($result->n1PassengerTransport);
        $this->assertNull($result->n1RemovableSecondRowSeat);
        $this->assertNull($result->n1SkiLiftUse);
        $this->assertNull($result->underlyingCombustionEngineType);
    }

    /**
     * @return array<string, mixed>
     */
    private function ivecoCamionnetteResponse(): array
    {
        return [
            'code' => 200,
            'country' => 'FR',
            'query' => 'AG-371-SB',
            'error' => false,
            'message' => 'Succès',
            'data' => [
                'AWN_marque' => 'IVECO',
                'AWN_modele' => '35C13',
                'AWN_VIN' => 'ZCFC3594005808662',
                'AWN_couleur' => 'VERT C',
                'AWN_date_mise_en_circulation_us' => '2009-12-07',
                'AWN_categorie_vehicule' => '35',
                'AWN_carrosserie_carte_grise' => 'BENNE',
                'AWN_genre' => 'CTTE',
                'AWN_genre_label' => 'Camionnette (PTAC ≤ 3.5 tonnes)',
                'AWN_nbr_de_places' => '3',
                'AWN_energie' => 'GAZOLE',
                'AWN_norme_euro' => 'INCONNU',
                'AWN_env_class' => 'EURO4',
                'AWN_emission_co_2' => '250',
                'AWN_puissance_fiscale' => '8',
                'AWN_PV' => '0',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function renaultClioResponse(): array
    {
        return [
            'code' => 200,
            'country' => 'FR',
            'query' => 'FH-034-DD',
            'error' => false,
            'data' => [
                'AWN_marque' => 'RENAULT',
                'AWN_modele' => 'CLIO',
                'AWN_VIN' => 'VF1R9800962986572',
                'AWN_couleur' => 'GRIS',
                'AWN_date_mise_en_circulation_us' => '2019-06-20',
                'AWN_genre' => 'VP',
                'AWN_carrosserie_carte_grise' => 'CI',
                'AWN_nbr_de_places' => '5',
                'AWN_energie' => 'GAZOLE',
                'AWN_norme_euro' => 'EURO 6',
                'AWN_emission_co_2' => '104',
                'AWN_puissance_fiscale' => '5',
            ],
        ];
    }

    private function makeDriver(): ApiPlaqueVehicleRegistryLookupDriver
    {
        return $this->app->make(ApiPlaqueVehicleRegistryLookupDriver::class);
    }
}
