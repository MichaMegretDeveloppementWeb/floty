<?php

declare(strict_types=1);

namespace App\Managers\VehicleRegistryLookup\Support;

final class LicensePlateNormalizer
{
    /**
     * Strip non-alphanumeric characters and uppercase the result.
     */
    public static function normalize(string $licensePlate): string
    {
        $stripped = preg_replace('/[^A-Za-z0-9]+/', '', $licensePlate) ?? '';

        return strtoupper($stripped);
    }
}
