<?php

declare(strict_types=1);

namespace App\Enums\VehicleRegistryLookup;

/**
 * Provider de données registre véhicules.
 *
 * - `Fake` : strategy stub utilisée en tests + dev local. Refusée en
 *   production par la factory (cf. `VehicleRegistryLookupStrategyFactory::isAvailable()`).
 * - `AaaData` : provider production (partenaire officiel SIV). Strategy
 *   non implémentée tant que le contrat fournisseur n'est pas signé ·
 *   la factory `isAvailable()` détecte programmatiquement l'absence
 *   d'implémentation et désactive la feature côté UI + backend.
 */
enum RegistryLookupProvider: string
{
    case Fake = 'fake';
    case AaaData = 'aaa_data';

    public function label(): string
    {
        return match ($this) {
            self::Fake => 'Fake (tests / dev)',
            self::AaaData => 'AAA Data',
        };
    }
}
