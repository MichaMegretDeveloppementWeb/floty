<?php

declare(strict_types=1);

namespace App\Enums\Declaration;

/**
 * Motif d'invalidation d'une déclaration (ADR-0004).
 *
 * Renseigné par le service `DeclarationInvalidationDetector` (phase 11)
 * lorsque le hash recalculé du snapshot diffère du hash stocké.
 *
 * Correspondance avec les événements déclencheurs :
 *   - `ContractModified`              ← event `ContractChanged` (phase 11)
 *   - `VehicleCharacteristicsChanged` ← modification `vehicle_fiscal_characteristics`
 *                                        ou d'un champ direct du véhicule
 *   - `UnavailabilityChanged`         ← event `UnavailabilityChanged`,
 *                                        uniquement si l'indispo est fiscale (fourrière)
 *   - `RuleChanged`                   ← correction d'une classe Rule côté code
 *                                        (pas de version interne — cf. ADR-0009)
 *   - `Other`                         ← cas non anticipé, fallback explicite
 */
enum InvalidationReason: string
{
    case ContractModified = 'contract_modified';
    case VehicleCharacteristicsChanged = 'vehicle_characteristics_changed';
    case UnavailabilityChanged = 'unavailability_changed';
    case RuleChanged = 'rule_changed';
    case Other = 'other';
}
