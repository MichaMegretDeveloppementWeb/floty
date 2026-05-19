<?php

declare(strict_types=1);

namespace App\Exceptions\VehicleRegistryLookup;

use App\Enums\VehicleRegistryLookup\RegistryLookupProvider;
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
    public static function noProviderConfigured(?string $configuredValue = null): self
    {
        $hint = $configuredValue === null || $configuredValue === ''
            ? 'no driver set'
            : "invalid driver value [{$configuredValue}]";

        return new self(
            technicalMessage: "Vehicle registry lookup has no usable provider ({$hint}).",
            userMessage: 'Aucun fournisseur de données véhicule n\'est configuré.',
        );
    }

    /**
     * Build the exception when the configured provider has no concrete strategy yet.
     */
    public static function providerNotImplemented(RegistryLookupProvider $provider): self
    {
        return new self(
            technicalMessage: "Vehicle registry lookup strategy for provider [{$provider->value}] is not implemented yet.",
            userMessage: 'Le fournisseur de données configuré n\'est pas encore disponible. Saisie manuelle uniquement.',
        );
    }

    /**
     * Build the exception when the provider is not allowed in the current environment.
     */
    public static function providerRefusedInEnvironment(RegistryLookupProvider $provider, string $env): self
    {
        return new self(
            technicalMessage: "Vehicle registry lookup provider [{$provider->value}] is not allowed in [{$env}] environment.",
            userMessage: 'Le fournisseur de données configuré n\'est pas autorisé sur cet environnement.',
        );
    }

    /**
     * Build the exception when the upstream provider fails for an unspecified reason.
     */
    public static function fromProviderFailure(string $provider, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "Vehicle registry provider [{$provider}] failed: {$reason}",
            userMessage: 'Le service de récupération de données est temporairement indisponible. Réessayez plus tard ou saisissez les informations manuellement.',
            previous: $previous,
        );
    }
}
