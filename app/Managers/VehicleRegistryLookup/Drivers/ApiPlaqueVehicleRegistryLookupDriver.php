<?php

declare(strict_types=1);

namespace App\Managers\VehicleRegistryLookup\Drivers;

use App\Contracts\VehicleRegistryLookup\VehicleRegistryLookupInterface;
use App\Data\User\Vehicle\VehicleRegistryLookupResultData;
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupRateLimitedException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupTimeoutException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;
use App\Managers\VehicleRegistryLookup\Drivers\Mapping\ApiPlaqueResponseMapper;
use App\Managers\VehicleRegistryLookup\Support\LicensePlateNormalizer;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP driver for the api-plaque.com service (Auto-Ways AUTO-NOW v1.1
 * via RapidAPI). Forwards an upper-cased license plate, maps the
 * response with {@see ApiPlaqueResponseMapper}, and raises typed
 * exceptions on non-2xx responses.
 */
final readonly class ApiPlaqueVehicleRegistryLookupDriver implements VehicleRegistryLookupInterface
{
    private const PROVIDER_KEY = 'api_plaque';

    public function __construct(
        private ConfigRepository $config,
        private ApiPlaqueResponseMapper $mapper,
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

        return $this->mapper->map($normalised, $data);
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
}
