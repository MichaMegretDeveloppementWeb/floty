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
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupRateLimitedException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupTimeoutException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;
use App\Managers\VehicleRegistryLookup\Support\LicensePlateNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP driver for the api-plaque.com service (Auto-Ways AUTO-NOW v1.1
 * via RapidAPI). The response mapping is embedded as private methods
 * to keep the full provider-specific knowledge in a single file.
 */
final readonly class ApiPlaqueVehicleRegistryLookupDriver implements VehicleRegistryLookupInterface
{
    private const PROVIDER_KEY = 'api_plaque';

    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function driverName(): RegistryLookupDriver
    {
        return RegistryLookupDriver::ApiPlaque;
    }

    /**
     * @throws VehicleNotFoundException
     * @throws RegistryLookupTimeoutException
     * @throws RegistryLookupRateLimitedException
     * @throws RegistryLookupUnavailableException
     */
    public function lookup(string $licensePlate): VehicleRegistryLookupResultData
    {
        $normalised = LicensePlateNormalizer::normalize($licensePlate);
        $config = $this->loadConfig();

        try {
            $response = Http::withHeaders([
                'X-RapidAPI-Host' => $config['host'],
                'X-RapidAPI-Key' => $config['api_key'],
                'Accept' => 'application/json',
            ])
                ->timeout($config['timeout_seconds'])
                ->get($config['base_url'].'/', ['plaque' => $normalised]);
        } catch (ConnectionException $e) {
            throw RegistryLookupTimeoutException::afterSeconds($config['timeout_seconds'], $e);
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            throw RegistryLookupRateLimitedException::fromProvider(
                self::PROVIDER_KEY,
                is_numeric($retryAfter) ? (int) $retryAfter : null,
            );
        }

        if (! $response->successful()) {
            throw RegistryLookupUnavailableException::fromDriverFailure(
                self::PROVIDER_KEY,
                "HTTP {$response->status()}: ".$response->body(),
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['data'])) {
            throw RegistryLookupUnavailableException::fromDriverFailure(
                self::PROVIDER_KEY,
                'Malformed response payload.',
            );
        }

        if (($payload['error'] ?? false) === true) {
            throw VehicleNotFoundException::forPlate($normalised);
        }

        $data = $payload['data'];
        if (! is_array($data) || $data === []) {
            throw VehicleNotFoundException::forPlate($normalised);
        }

        return $this->mapResponse($normalised, $data);
    }

    /**
     * Transform a raw provider response into the Floty DTO.
     *
     * @param  array<string, mixed>  $data
     */
    private function mapResponse(string $normalisedPlate, array $data): VehicleRegistryLookupResultData
    {
        $registrationDate = $this->nonEmpty($data['AWN_date_mise_en_circulation_us'] ?? null);
        $homologationMethod = $this->deriveHomologationMethod($registrationDate);
        $co2 = $this->intOrNull($data['AWN_emission_co_2'] ?? null);

        return new VehicleRegistryLookupResultData(
            licensePlate: $normalisedPlate,
            brand: $this->titleCase($this->nonEmpty($data['AWN_marque'] ?? null)),
            model: $this->titleCase($this->nonEmpty($data['AWN_modele'] ?? null)),
            vin: $this->nonEmpty($data['AWN_VIN'] ?? null),
            color: $this->titleCase($this->nonEmpty($data['AWN_couleur'] ?? null)),
            firstFrenchRegistrationDate: $registrationDate,
            firstOriginRegistrationDate: $registrationDate,
            receptionCategory: $this->mapReceptionCategory($this->nonEmpty($data['AWN_genre'] ?? null)),
            bodyType: $this->mapBodyType(
                $this->nonEmpty($data['AWN_carrosserie_carte_grise'] ?? null),
            ),
            seatsCount: $this->intOrNull($data['AWN_nbr_de_places'] ?? null),
            energySource: $this->mapEnergySource($this->nonEmpty($data['AWN_energie'] ?? null)),
            underlyingCombustionEngineType: null,
            euroStandard: $this->mapEuroStandard($this->firstNonEmpty(
                $data['AWN_norme_euro'] ?? null,
                $data['AWN_env_class'] ?? null,
                $data['AWN_env_class_ref'] ?? null,
            )),
            homologationMethod: $homologationMethod,
            co2Wltp: $homologationMethod === HomologationMethod::Wltp ? $co2 : null,
            co2Nedc: $homologationMethod === HomologationMethod::Nedc ? $co2 : null,
            taxableHorsepower: $this->intOrNull($data['AWN_puissance_fiscale'] ?? null),
            kerbMass: $this->intOrNull($data['AWN_PV'] ?? null),
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
     * Read and validate the provider configuration.
     *
     * @return array{base_url: string, host: string, api_key: string, timeout_seconds: int}
     *
     * @throws RegistryLookupUnavailableException
     */
    private function loadConfig(): array
    {
        $baseUrl = $this->config->get('vehicle-registry.providers.api_plaque.base_url');
        $host = $this->config->get('vehicle-registry.providers.api_plaque.host');
        $apiKey = $this->config->get('vehicle-registry.providers.api_plaque.api_key');
        $timeout = $this->config->get('vehicle-registry.providers.api_plaque.timeout_seconds');

        if (! is_string($baseUrl) || $baseUrl === ''
            || ! is_string($host) || $host === ''
            || ! is_string($apiKey) || $apiKey === ''
        ) {
            throw RegistryLookupUnavailableException::fromDriverFailure(
                self::PROVIDER_KEY,
                'Missing base_url, host or api_key in config.',
            );
        }

        return [
            'base_url' => rtrim($baseUrl, '/'),
            'host' => $host,
            'api_key' => $apiKey,
            'timeout_seconds' => is_int($timeout) ? $timeout : 10,
        ];
    }

    /**
     * Treat "INCONNU", empty string, "0" as absent for string fields.
     */
    private function nonEmpty(mixed $value): ?string
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
     * Return the first value that passes the nonEmpty check.
     */
    private function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $resolved = $this->nonEmpty($value);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * Parse a numeric-looking string to int, treating "0" and "INCONNU" as null.
     */
    private function intOrNull(mixed $value): ?int
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
    private function titleCase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_convert_case(mb_strtolower($value), MB_CASE_TITLE);
    }

    /**
     * Derive Floty's M1/N1 from the French "genre national" field.
     *
     * AUTO-NOW returns the national vehicle genre code (VP, CTTE, VU,
     * CAM, etc.), not the European reception category (M1/N1). The
     * mapping is well established by the French SIV.
     */
    private function mapReceptionCategory(?string $genre): ?ReceptionCategory
    {
        if ($genre === null) {
            return null;
        }

        return match (mb_strtoupper($genre)) {
            'VP', 'CL', 'VASP', 'M1' => ReceptionCategory::M1,
            'CTTE', 'VU', 'CAM', 'TRR', 'TCP', 'N1' => ReceptionCategory::N1,
            default => null,
        };
    }

    /**
     * Match the carte-grise body label against Floty's 5 enum cases.
     */
    private function mapBodyType(?string $raw): ?BodyType
    {
        if ($raw === null) {
            return null;
        }

        return match (mb_strtoupper($raw)) {
            'CI', 'BERLINE', 'CONDUITE INTERIEURE' => BodyType::InteriorDriving,
            'BB', 'BREAK' => BodyType::StationWagon,
            'CTTE', 'CAMIONNETTE', 'FOURGON', 'FOURGONNETTE' => BodyType::LightTruck,
            'BE', 'PICK-UP', 'PICKUP' => BodyType::Pickup,
            'HB', 'HANDICAP' => BodyType::Handicap,
            default => null,
        };
    }

    /**
     * Map AUTO-NOW energy labels to Floty's EnergySource enum.
     */
    private function mapEnergySource(?string $raw): ?EnergySource
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
    private function mapEuroStandard(?string $raw): ?EuroStandard
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
    private function deriveHomologationMethod(?string $isoDate): ?HomologationMethod
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
