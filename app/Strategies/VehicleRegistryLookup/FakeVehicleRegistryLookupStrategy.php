<?php

declare(strict_types=1);

namespace App\Strategies\VehicleRegistryLookup;

use App\Contracts\VehicleRegistryLookup\VehicleRegistryLookupInterface;
use App\Data\User\Vehicle\VehicleRegistryLookupResultData;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\VehicleRegistryLookup\RegistryLookupProvider;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;
use App\Strategies\VehicleRegistryLookup\Support\LicensePlateNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Strategy stub pour tests automatisés et développement local.
 *
 * Refusée en production par la factory (cf. `VehicleRegistryLookupStrategyFactory::isAvailable()`).
 *
 * Source des données :
 *   - fixtures injectées via `config('vehicle-registry.providers.fake.fixtures')`
 *     (clé = plaque normalisée, valeur = tableau partiel matchant les
 *     champs de {@see VehicleRegistryLookupResultData}) ·
 *   - sinon un panel de plaques prédéfinies couvrant les 7 cas de test
 *     de référence définis dans le workflow fournisseur (essence Euro 6,
 *     diesel Euro 6, électrique, hybride rechargeable, utilitaire N1,
 *     pre-2004 PA, etc.) · permet à un dev/test d'exercer tout le
 *     parcours UI sans configuration supplémentaire.
 *
 * Comportement déterministe · les mêmes plaques renvoient les mêmes
 * données dans toute l'application (utile pour les snapshots et les
 * tests E2E).
 */
final readonly class FakeVehicleRegistryLookupStrategy implements VehicleRegistryLookupInterface
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function provider(): RegistryLookupProvider
    {
        return RegistryLookupProvider::Fake;
    }

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
            sourceProvider: RegistryLookupProvider::Fake,
            fetchedAt: CarbonImmutable::now()->toIso8601String(),
        );
    }

    /**
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
     * Panel de fixtures par défaut. Plaques fictives au format SIV
     * post-2009 (`AA-123-AA`). Couvre les 7 cas de test du workflow
     * fournisseur pour valider le parcours UI complet.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function defaultFixtures(): array
    {
        return [
            // Cas 1 · véhicule récent essence Euro 6 (M1, cas le plus courant)
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

            // Cas 2 · diesel Euro 6 (M1 · pollutant = MostPolluting)
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

            // Cas 3 · véhicule électrique (M1 · pollutant = E, exempté)
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

            // Cas 4 · hybride rechargeable essence (sous-jacent essence)
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

            // Cas 5 · utilitaire N1 (CTTE)
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

            // Cas 6 · véhicule ancien pre-2004 (pas de CO₂ · homologation PA)
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

            // Cas 7 · pick-up N1 (BE)
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
