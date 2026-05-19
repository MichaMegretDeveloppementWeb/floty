<?php

declare(strict_types=1);

namespace App\Managers\VehicleRegistryLookup\Drivers\Mapping;

use App\Data\User\Vehicle\VehicleRegistryLookupResultData;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use Carbon\CarbonImmutable;

/**
 * Transform a raw api-plaque.com (Auto-Ways AUTO-NOW) response into
 * the Floty DTO. Handles French-specific encodings (energy in upper
 * case, Euro norm strings, body type codes) and derives
 * homologation_method + CO2 channel from the registration date.
 */
final class ApiPlaqueResponseMapper
{
    /**
     * Build the DTO from the provider's JSON payload (the `data` object).
     *
     * @param  array<string, mixed>  $data
     */
    public function map(string $normalisedPlate, array $data): VehicleRegistryLookupResultData
    {
        $registrationDate = self::nonEmpty($data['AWN_date_mise_en_circulation_us'] ?? null);
        $homologationMethod = self::deriveHomologationMethod($registrationDate);
        $co2 = self::intOrNull($data['AWN_emission_co_2'] ?? null);

        return new VehicleRegistryLookupResultData(
            licensePlate: $normalisedPlate,
            brand: self::titleCase(self::nonEmpty($data['AWN_marque'] ?? null)),
            model: self::titleCase(self::nonEmpty($data['AWN_modele'] ?? null)),
            vin: self::nonEmpty($data['AWN_VIN'] ?? null),
            color: self::titleCase(self::nonEmpty($data['AWN_couleur'] ?? null)),
            firstFrenchRegistrationDate: $registrationDate,
            firstOriginRegistrationDate: $registrationDate,
            receptionCategory: self::enumOrNull(
                ReceptionCategory::class,
                self::nonEmpty($data['AWN_categorie_vehicule'] ?? null),
            ),
            bodyType: self::enumOrNull(
                BodyType::class,
                self::nonEmpty($data['AWN_carrosserie_carte_grise'] ?? null),
            ),
            seatsCount: self::intOrNull($data['AWN_nbr_de_places'] ?? null),
            energySource: self::mapEnergySource(self::nonEmpty($data['AWN_energie'] ?? null)),
            underlyingCombustionEngineType: null,
            euroStandard: self::mapEuroStandard(self::nonEmpty($data['AWN_norme_euro'] ?? null)),
            homologationMethod: $homologationMethod,
            co2Wltp: $homologationMethod === HomologationMethod::Wltp ? $co2 : null,
            co2Nedc: $homologationMethod === HomologationMethod::Nedc ? $co2 : null,
            taxableHorsepower: self::intOrNull($data['AWN_puissance_fiscale'] ?? null),
            kerbMass: self::intOrNull($data['AWN_PV'] ?? null),
            acceptsE85: null,
            handicapAccess: null,
            m1SpecialUse: null,
            n1PassengerTransport: null,
            n1RemovableSecondRowSeat: null,
            n1SkiLiftUse: null,
            sourceDriver: RegistryLookupDriver::ApiPlaque,
            fetchedAt: CarbonImmutable::now()->toIso8601String(),
        );
    }

    /**
     * Treat "INCONNU", empty string, "0" as absent for string fields.
     */
    private static function nonEmpty(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === 'INCONNU' || $trimmed === '0') {
            return null;
        }

        return $trimmed;
    }

    /**
     * Parse a numeric-looking string to int, treating "0" and "INCONNU" as null.
     */
    private static function intOrNull(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $str = trim((string) $value);
        if ($str === '' || $str === '0' || $str === 'INCONNU') {
            return null;
        }

        if (! is_numeric($str)) {
            return null;
        }

        return (int) $str;
    }

    /**
     * Convert "RENAULT", "GAZOLE" style upper-case strings to "Renault", "Gazole".
     */
    private static function titleCase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_convert_case(mb_strtolower($value), MB_CASE_TITLE);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T|null
     */
    private static function enumOrNull(string $enumClass, ?string $value): ?\BackedEnum
    {
        if ($value === null) {
            return null;
        }

        /** @var T|null $resolved */
        $resolved = $enumClass::tryFrom($value);

        return $resolved;
    }

    /**
     * Map AUTO-NOW energy labels to Floty's EnergySource enum.
     */
    private static function mapEnergySource(?string $raw): ?EnergySource
    {
        if ($raw === null) {
            return null;
        }

        return match (mb_strtoupper($raw)) {
            'ESSENCE' => EnergySource::Gasoline,
            'GAZOLE', 'DIESEL' => EnergySource::Diesel,
            'ELECTRIQUE', 'ELECTRICITE' => EnergySource::Electric,
            'HYDROGENE' => EnergySource::Hydrogen,
            'HYBRIDE RECHARGEABLE', 'HYBRIDE ESSENCE RECHARGEABLE' => EnergySource::PluginHybrid,
            'HYBRIDE NON RECHARGEABLE', 'HYBRIDE ESSENCE', 'HYBRIDE' => EnergySource::NonPluginHybrid,
            'GPL' => EnergySource::Lpg,
            'GNV', 'GAZ NATUREL' => EnergySource::Cng,
            'E85', 'SUPERETHANOL' => EnergySource::E85,
            'ELECTRIQUE HYDROGENE' => EnergySource::ElectricHydrogen,
            default => null,
        };
    }

    /**
     * Map AUTO-NOW Euro labels to Floty's EuroStandard enum.
     */
    private static function mapEuroStandard(?string $raw): ?EuroStandard
    {
        if ($raw === null) {
            return null;
        }

        $normalised = mb_strtoupper(str_replace([' ', '-', '_'], '', $raw));

        return match ($normalised) {
            'EURO1' => EuroStandard::Euro1,
            'EURO2' => EuroStandard::Euro2,
            'EURO3' => EuroStandard::Euro3,
            'EURO4' => EuroStandard::Euro4,
            'EURO5' => EuroStandard::Euro5,
            'EURO5A' => EuroStandard::Euro5a,
            'EURO5B' => EuroStandard::Euro5b,
            'EURO6' => EuroStandard::Euro6,
            'EURO6B' => EuroStandard::Euro6b,
            'EURO6C' => EuroStandard::Euro6c,
            'EURO6DTEMP', 'EURO6DTEMPEVAP', 'EURO6DTEMPEVAPISC' => EuroStandard::Euro6dTemp,
            'EURO6D' => EuroStandard::Euro6d,
            'EURO6DISC' => EuroStandard::Euro6dIsc,
            'EURO6DISCFCM' => EuroStandard::Euro6dIscFcm,
            default => null,
        };
    }

    /**
     * Derive the homologation method from the French registration date.
     */
    private static function deriveHomologationMethod(?string $isoDate): ?HomologationMethod
    {
        if ($isoDate === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($isoDate);
        } catch (\Throwable) {
            return null;
        }

        if ($date->greaterThanOrEqualTo(CarbonImmutable::create(2020, 3, 1))) {
            return HomologationMethod::Wltp;
        }

        if ($date->year >= 2004) {
            return HomologationMethod::Nedc;
        }

        return HomologationMethod::Pa;
    }
}
