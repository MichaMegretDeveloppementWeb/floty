<?php

declare(strict_types=1);

namespace App\Exceptions\VehicleRegistryLookup;

use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use App\Exceptions\BaseAppException;
use Throwable;

final class RegistryLookupUnavailableException extends BaseAppException
{
    /**
     * Build the exception when the feature is globally disabled.
     */
    public static function featureDisabled(): self
    {
        return new self(
            technicalMessage: 'Vehicle registry lookup feature is disabled via config.',
            userMessage: 'La récupération automatique depuis la plaque n\'est pas activée sur cette installation.',
        );
    }

    /**
     * Build the exception when no valid driver is configured.
     */
    public static function noDriverConfigured(?string $configuredValue = null): self
    {
        $hint = $configuredValue === null || $configuredValue === ''
            ? 'no driver set'
            : "invalid driver value [{$configuredValue}]";

        return new self(
            technicalMessage: "Vehicle registry lookup has no usable driver ({$hint}).",
            userMessage: 'Aucun fournisseur de données véhicule n\'est configuré.',
        );
    }

    /**
     * Build the exception when the configured driver has no concrete implementation yet.
     */
    public static function driverNotImplemented(RegistryLookupDriver $driver): self
    {
        return new self(
            technicalMessage: "Vehicle registry lookup driver [{$driver->value}] is not implemented yet.",
            userMessage: 'Le fournisseur de données configuré n\'est pas encore disponible. Saisie manuelle uniquement.',
        );
    }

    /**
     * Build the exception when the driver is not allowed in the current environment.
     */
    public static function driverRefusedInEnvironment(RegistryLookupDriver $driver, string $env): self
    {
        return new self(
            technicalMessage: "Vehicle registry lookup driver [{$driver->value}] is not allowed in [{$env}] environment.",
            userMessage: 'Le fournisseur de données configuré n\'est pas autorisé sur cet environnement.',
        );
    }

    /**
     * Build the exception when the upstream driver fails for an unspecified reason.
     */
    public static function fromDriverFailure(string $driver, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "Vehicle registry driver [{$driver}] failed: {$reason}",
            userMessage: 'Le service de récupération de données est temporairement indisponible. Réessayez plus tard ou saisissez les informations manuellement.',
            previous: $previous,
        );
    }
}
