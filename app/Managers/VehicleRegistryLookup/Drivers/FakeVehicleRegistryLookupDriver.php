<?php

declare(strict_types=1);

namespace App\Managers\VehicleRegistryLookup\Drivers;

use App\Contracts\VehicleRegistryLookup\VehicleRegistryLookupInterface;
use App\Data\User\Vehicle\VehicleRegistryLookupResultData;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;
use App\Managers\VehicleRegistryLookup\Support\LicensePlateNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Stub driver backed by config fixtures and a built-in default set.
 * Forbidden in production by the manager's environment guard.
 */
final readonly class FakeVehicleRegistryLookupDriver implements VehicleRegistryLookupInterface
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function driverName(): RegistryLookupDriver
    {
        return RegistryLookupDriver::Fake;
    }

    /**
     * @throws VehicleNotFoundException
     */
    public function lookup(string $licensePlate): VehicleRegistryLookupResultData
    {
        $plate = LicensePlateNormalizer::normalize($licensePlate);

        $fixtures = $this->loadFixtures();

        if (! array_key_exists($plate, $fixtures)) {
            throw VehicleNotFoundException::forPlate($plate);
        }

        $data = $fixtures[$plate];

        return new VehicleRegistryLookupResultData(
            licensePlate: $plate,
            brand: $data['brand'] ?? null,
            model: $data['model'] ?? null,
            vin: $data['vin'] ?? null,
            color: $data['color'] ?? null,
            firstFrenchRegistrationDate: $data['firstFrenchRegistrationDate'] ?? null,
            firstOriginRegistrationDate: $data['firstOriginRegistrationDate'] ?? null,
            receptionCategory: $this->toEnum(ReceptionCategory::class, $data['receptionCategory'] ?? null),
            bodyType: $this->toEnum(BodyType::class, $data['bodyType'] ?? null),
            seatsCount: $data['seatsCount'] ?? null,
            energySource: $this->toEnum(EnergySource::class, $data['energySource'] ?? null),
            underlyingCombustionEngineType: $this->toEnum(UnderlyingCombustionEngineType::class, $data['underlyingCombustionEngineType'] ?? null),
            euroStandard: $this->toEnum(EuroStandard::class, $data['euroStandard'] ?? null),
            homologationMethod: $this->toEnum(HomologationMethod::class, $data['homologationMethod'] ?? null),
            co2Wltp: $data['co2Wltp'] ?? null,
            co2Nedc: $data['co2Nedc'] ?? null,
            taxableHorsepower: $data['taxableHorsepower'] ?? null,
            kerbMass: $data['kerbMass'] ?? null,
            acceptsE85: $data['acceptsE85'] ?? null,
            handicapAccess: $data['handicapAccess'] ?? null,
            m1SpecialUse: $data['m1SpecialUse'] ?? null,
            n1PassengerTransport: $data['n1PassengerTransport'] ?? null,
            n1RemovableSecondRowSeat: $data['n1RemovableSecondRowSeat'] ?? null,
            n1SkiLiftUse: $data['n1SkiLiftUse'] ?? null,
            sourceDriver: RegistryLookupDriver::Fake,
            fetchedAt: CarbonImmutable::now()->toIso8601String(),
        );
    }

    /**
     * Merge user-defined fixtures (normalised keys) over the built-in defaults.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadFixtures(): array
    {
        /** @var array<string, array<string, mixed>> $configured */
        $configured = $this->config->get('vehicle-registry.providers.fake.fixtures', []);

        $normalized = [];
        foreach ($configured as $plate => $payload) {
            $normalized[LicensePlateNormalizer::normalize((string) $plate)] = $payload;
        }

        return $normalized + self::defaultFixtures();
    }

    /**
     * Built-in fixtures covering representative vehicle profiles for local testing.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function defaultFixtures(): array
    {
        return [
            'AA123AA' => [
                'brand' => 'Peugeot',
                'model' => '308',
                'vin' => 'VF3LBHZRWKS123456',
                'color' => 'Gris',
                'firstFrenchRegistrationDate' => '2022-03-15',
                'firstOriginRegistrationDate' => '2022-03-15',
                'receptionCategory' => 'M1',
                'bodyType' => 'CI',
                'seatsCount' => 5,
                'energySource' => 'gasoline',
                'euroStandard' => 'euro_6d_isc_fcm',
                'homologationMethod' => 'WLTP',
                'co2Wltp' => 122,
                'taxableHorsepower' => 6,
                'kerbMass' => 1320,
            ],

            'BB456BB' => [
                'brand' => 'Renault',
                'model' => 'Mégane Estate',
                'vin' => 'VF1RFB00X65123456',
                'color' => 'Noir',
                'firstFrenchRegistrationDate' => '2021-06-10',
                'firstOriginRegistrationDate' => '2021-06-10',
                'receptionCategory' => 'M1',
                'bodyType' => 'BB',
                'seatsCount' => 5,
                'energySource' => 'diesel',
                'euroStandard' => 'euro_6d',
                'homologationMethod' => 'WLTP',
                'co2Wltp' => 138,
                'taxableHorsepower' => 7,
                'kerbMass' => 1450,
            ],

            'CC789CC' => [
                'brand' => 'Tesla',
                'model' => 'Model 3',
                'vin' => '5YJ3E1EA7KF123456',
                'color' => 'Blanc',
                'firstFrenchRegistrationDate' => '2023-09-01',
                'firstOriginRegistrationDate' => '2023-09-01',
                'receptionCategory' => 'M1',
                'bodyType' => 'CI',
                'seatsCount' => 5,
                'energySource' => 'electric',
                'homologationMethod' => 'WLTP',
                'co2Wltp' => 0,
                'taxableHorsepower' => 5,
                'kerbMass' => 1760,
            ],

            'DD012DD' => [
                'brand' => 'Toyota',
                'model' => 'Prius',
                'vin' => 'JTDKBRFU8K3123456',
                'color' => 'Bleu',
                'firstFrenchRegistrationDate' => '2022-11-20',
                'firstOriginRegistrationDate' => '2022-11-20',
                'receptionCategory' => 'M1',
                'bodyType' => 'CI',
                'seatsCount' => 5,
                'energySource' => 'plugin_hybrid',
                'underlyingCombustionEngineType' => 'gasoline',
                'euroStandard' => 'euro_6d_isc_fcm',
                'homologationMethod' => 'WLTP',
                'co2Wltp' => 28,
                'taxableHorsepower' => 5,
                'kerbMass' => 1530,
            ],

            'EE345EE' => [
                'brand' => 'Citroën',
                'model' => 'Berlingo Van',
                'vin' => 'VF7BJEHZMK9123456',
                'color' => 'Blanc',
                'firstFrenchRegistrationDate' => '2020-04-12',
                'firstOriginRegistrationDate' => '2020-04-12',
                'receptionCategory' => 'N1',
                'bodyType' => 'CTTE',
                'seatsCount' => 2,
                'energySource' => 'diesel',
                'euroStandard' => 'euro_6d',
                'homologationMethod' => 'WLTP',
                'co2Wltp' => 145,
                'taxableHorsepower' => 6,
                'kerbMass' => 1380,
            ],

            'FF678FF' => [
                'brand' => 'Volkswagen',
                'model' => 'Golf IV',
                'vin' => 'WVWZZZ1JZ1W123456',
                'color' => 'Rouge',
                'firstFrenchRegistrationDate' => '2001-07-08',
                'firstOriginRegistrationDate' => '2001-07-08',
                'receptionCategory' => 'M1',
                'bodyType' => 'CI',
                'seatsCount' => 5,
                'energySource' => 'gasoline',
                'euroStandard' => 'euro_3',
                'homologationMethod' => 'PA',
                'taxableHorsepower' => 5,
                'kerbMass' => 1180,
            ],

            'GG901GG' => [
                'brand' => 'Ford',
                'model' => 'Ranger',
                'vin' => 'WF0LMFEL6KU123456',
                'color' => 'Vert',
                'firstFrenchRegistrationDate' => '2023-02-28',
                'firstOriginRegistrationDate' => '2023-02-28',
                'receptionCategory' => 'N1',
                'bodyType' => 'BE',
                'seatsCount' => 5,
                'energySource' => 'diesel',
                'euroStandard' => 'euro_6d',
                'homologationMethod' => 'WLTP',
                'co2Wltp' => 215,
                'taxableHorsepower' => 9,
                'kerbMass' => 2150,
            ],
        ];
    }

    /**
     * Resolve a string into a backed enum case, or null when missing.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T|null
     */
    private function toEnum(string $enumClass, ?string $value): ?\BackedEnum
    {
        if ($value === null) {
            return null;
        }

        /** @var T|null $resolved */
        $resolved = $enumClass::tryFrom($value);

        return $resolved;
    }
}
