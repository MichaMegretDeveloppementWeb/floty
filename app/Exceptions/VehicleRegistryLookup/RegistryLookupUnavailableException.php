<?php

declare(strict_types=1);

namespace App\Exceptions\VehicleRegistryLookup;

use App\Enums\VehicleRegistryLookup\RegistryLookupProvider;
use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Aucun provider opérationnel pour servir la requête.
 *
 * 4 cas distincts (factory `make()` + controller fall-back) :
 *   - Feature désactivée via `vehicle-registry.enabled = false`.
 *   - Aucun driver configuré (`vehicle-registry.default` vide ou
 *     valeur enum invalide).
 *   - Driver configuré pointe sur une strategy non implémentée (cas
 *     AAA Data avant signature contrat).
 *   - Provider configuré refusé dans l'environnement courant (cas
 *     Fake en production · garde-fou anti-déploiement accidentel).
 *
 * Renvoyée par le controller avec HTTP 503.
 */
final class RegistryLookupUnavailableException extends BaseAppException
{
    public static function featureDisabled(): self
    {
        return new self(
            technicalMessage: 'Vehicle registry lookup feature is disabled via config.',
            userMessage: 'La récupération automatique depuis la plaque n\'est pas activée sur cette installation.',
        );
    }

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

    public static function providerNotImplemented(RegistryLookupProvider $provider): self
    {
        return new self(
            technicalMessage: "Vehicle registry lookup strategy for provider [{$provider->value}] is not implemented yet.",
            userMessage: 'Le fournisseur de données configuré n\'est pas encore disponible. Saisie manuelle uniquement.',
        );
    }

    public static function providerRefusedInEnvironment(RegistryLookupProvider $provider, string $env): self
    {
        return new self(
            technicalMessage: "Vehicle registry lookup provider [{$provider->value}] is not allowed in [{$env}] environment.",
            userMessage: 'Le fournisseur de données configuré n\'est pas autorisé sur cet environnement.',
        );
    }

    public static function fromProviderFailure(string $provider, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "Vehicle registry provider [{$provider}] failed: {$reason}",
            userMessage: 'Le service de récupération de données est temporairement indisponible. Réessayez plus tard ou saisissez les informations manuellement.',
            previous: $previous,
        );
    }
}
