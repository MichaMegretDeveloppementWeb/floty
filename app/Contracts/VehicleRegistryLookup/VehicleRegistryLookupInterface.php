<?php

declare(strict_types=1);

namespace App\Contracts\VehicleRegistryLookup;

use App\Data\User\Vehicle\VehicleRegistryLookupResultData;
use App\Enums\VehicleRegistryLookup\RegistryLookupProvider;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupRateLimitedException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupTimeoutException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;

interface VehicleRegistryLookupInterface
{
    /**
     * Identifier of the provider serving the request.
     */
    public function provider(): RegistryLookupProvider;

    /**
     * Resolve a vehicle's characteristics from its license plate.
     *
     * @throws VehicleNotFoundException
     * @throws RegistryLookupTimeoutException
     * @throws RegistryLookupRateLimitedException
     * @throws RegistryLookupUnavailableException
     */
    public function lookup(string $licensePlate): VehicleRegistryLookupResultData;
}
