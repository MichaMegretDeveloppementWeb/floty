<?php

declare(strict_types=1);

namespace Tests\Unit\Managers\VehicleRegistryLookup\Drivers\Mapping;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use App\Managers\VehicleRegistryLookup\Drivers\Mapping\ApiPlaqueResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiPlaqueResponseMapperTest extends TestCase
{
    #[Test]
    public function mappe_une_reponse_renault_clio_diesel_2019(): void
    {
        $data = [
            'AWN_marque' => 'RENAULT',
            'AWN_modele' => 'CLIO',
            'AWN_VIN' => 'VF1R9800962986572',
            'AWN_couleur' => 'GRIS',
            'AWN_date_mise_en_circulation_us' => '2019-06-20',
            'AWN_categorie_vehicule' => 'M1',
            'AWN_carrosserie_carte_grise' => 'CI',
            'AWN_nbr_de_places' => '5',
            'AWN_energie' => 'GAZOLE',
            'AWN_norme_euro' => 'INCONNU',
            'AWN_emission_co_2' => '104',
            'AWN_puissance_fiscale' => '5',
            'AWN_PV' => '0',
        ];

        $result = $this->makeMapper()->map('FH034DD', $data);

        $this->assertSame('FH034DD', $result->licensePlate);
        $this->assertSame('Renault', $result->brand);
        $this->assertSame('Clio', $result->model);
        $this->assertSame('VF1R9800962986572', $result->vin);
        $this->assertSame('Gris', $result->color);
        $this->assertSame('2019-06-20', $result->firstFrenchRegistrationDate);
        $this->assertSame('2019-06-20', $result->firstOriginRegistrationDate);
        $this->assertSame(ReceptionCategory::M1, $result->receptionCategory);
        $this->assertSame(BodyType::InteriorDriving, $result->bodyType);
        $this->assertSame(5, $result->seatsCount);
        $this->assertSame(EnergySource::Diesel, $result->energySource);
        $this->assertNull($result->euroStandard);
        $this->assertSame(HomologationMethod::Nedc, $result->homologationMethod);
        $this->assertNull($result->co2Wltp);
        $this->assertSame(104, $result->co2Nedc);
        $this->assertSame(5, $result->taxableHorsepower);
        $this->assertNull($result->kerbMass);
        $this->assertSame(RegistryLookupDriver::ApiPlaque, $result->sourceDriver);
    }

    #[Test]
    public function deduit_wltp_pour_un_vehicule_immatricule_apres_mars_2020(): void
    {
        $data = [
            'AWN_date_mise_en_circulation_us' => '2022-05-10',
            'AWN_emission_co_2' => '125',
        ];

        $result = $this->makeMapper()->map('ZZ123ZZ', $data);

        $this->assertSame(HomologationMethod::Wltp, $result->homologationMethod);
        $this->assertSame(125, $result->co2Wltp);
        $this->assertNull($result->co2Nedc);
    }

    #[Test]
    public function deduit_pa_pour_un_vehicule_pre_2004(): void
    {
        $data = [
            'AWN_date_mise_en_circulation_us' => '2001-07-08',
        ];

        $result = $this->makeMapper()->map('ZZ123ZZ', $data);

        $this->assertSame(HomologationMethod::Pa, $result->homologationMethod);
    }

    #[Test]
    public function ignore_les_valeurs_inconnu_et_zero_pour_les_champs_string(): void
    {
        $data = [
            'AWN_marque' => 'INCONNU',
            'AWN_modele' => '',
            'AWN_VIN' => '0',
            'AWN_couleur' => '   ',
        ];

        $result = $this->makeMapper()->map('ZZ123ZZ', $data);

        $this->assertNull($result->brand);
        $this->assertNull($result->model);
        $this->assertNull($result->vin);
        $this->assertNull($result->color);
    }

    #[Test]
    #[DataProvider('energySourceVariants')]
    public function mappe_les_energies_aws_vers_l_enum_floty(string $awsLabel, ?EnergySource $expected): void
    {
        $result = $this->makeMapper()->map('ZZ123ZZ', ['AWN_energie' => $awsLabel]);

        $this->assertSame($expected, $result->energySource);
    }

    /**
     * @return iterable<string, array{string, ?EnergySource}>
     */
    public static function energySourceVariants(): iterable
    {
        yield 'ESSENCE' => ['ESSENCE', EnergySource::Gasoline];
        yield 'GAZOLE' => ['GAZOLE', EnergySource::Diesel];
        yield 'DIESEL' => ['DIESEL', EnergySource::Diesel];
        yield 'ELECTRIQUE' => ['ELECTRIQUE', EnergySource::Electric];
        yield 'HYBRIDE NON RECHARGEABLE' => ['HYBRIDE NON RECHARGEABLE', EnergySource::NonPluginHybrid];
        yield 'HYBRIDE RECHARGEABLE' => ['HYBRIDE RECHARGEABLE', EnergySource::PluginHybrid];
        yield 'GPL' => ['GPL', EnergySource::Lpg];
        yield 'GNV' => ['GNV', EnergySource::Cng];
        yield 'E85' => ['E85', EnergySource::E85];
        yield 'INCONNU' => ['INCONNU', null];
        yield 'libellé non mappé' => ['VAPEUR DE TIGRE', null];
    }

    #[Test]
    #[DataProvider('euroStandardVariants')]
    public function mappe_les_normes_euro_aws_vers_l_enum_floty(string $awsLabel, ?EuroStandard $expected): void
    {
        $result = $this->makeMapper()->map('ZZ123ZZ', ['AWN_norme_euro' => $awsLabel]);

        $this->assertSame($expected, $result->euroStandard);
    }

    /**
     * @return iterable<string, array{string, ?EuroStandard}>
     */
    public static function euroStandardVariants(): iterable
    {
        yield 'EURO 6' => ['EURO 6', EuroStandard::Euro6];
        yield 'EURO 6d' => ['EURO 6d', EuroStandard::Euro6d];
        yield 'EURO 6d-ISC-FCM' => ['EURO 6d-ISC-FCM', EuroStandard::Euro6dIscFcm];
        yield 'EURO 4' => ['EURO 4', EuroStandard::Euro4];
        yield 'INCONNU' => ['INCONNU', null];
    }

    #[Test]
    public function laisse_les_booleens_fiscaux_a_null(): void
    {
        $result = $this->makeMapper()->map('ZZ123ZZ', ['AWN_marque' => 'RENAULT']);

        $this->assertNull($result->acceptsE85);
        $this->assertNull($result->handicapAccess);
        $this->assertNull($result->m1SpecialUse);
        $this->assertNull($result->n1PassengerTransport);
        $this->assertNull($result->n1RemovableSecondRowSeat);
        $this->assertNull($result->n1SkiLiftUse);
    }

    #[Test]
    public function laisse_underlying_combustion_engine_a_null(): void
    {
        $result = $this->makeMapper()->map('ZZ123ZZ', ['AWN_energie' => 'HYBRIDE RECHARGEABLE']);

        $this->assertNull($result->underlyingCombustionEngineType);
    }

    private function makeMapper(): ApiPlaqueResponseMapper
    {
        return new ApiPlaqueResponseMapper;
    }
}
