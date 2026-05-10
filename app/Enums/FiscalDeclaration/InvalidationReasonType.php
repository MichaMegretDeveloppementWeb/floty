<?php

declare(strict_types=1);

namespace App\Enums\FiscalDeclaration;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Types d'événements pouvant invalider une déclaration fiscale
 * `generated` (ADR-0015 § D9 rev. 1.1).
 *
 * Toute action invalidante détectée par les Observers (D3) doit
 * empiler une entrée dans `fiscal_declarations.obsolete_reasons` avec
 * un `type` strictement parmi ces valeurs. Tout autre type est rejeté
 * à l'insertion (validation côté `MarkAsObsoleteAction`).
 *
 * La liste exhaustive des actions effectivement invalidantes (vs
 * non-invalidantes) est cadrée à l'ADR-0015 § D8.
 */
#[TypeScript]
enum InvalidationReasonType: string
{
    case ContractCreated = 'contract_created';
    case ContractUpdated = 'contract_updated';
    case ContractDeleted = 'contract_deleted';

    case VfcCreated = 'vfc_created';
    case VfcUpdated = 'vfc_updated';
    case VfcDeleted = 'vfc_deleted';

    case UnavailabilityCreated = 'unavailability_created';
    case UnavailabilityUpdated = 'unavailability_updated';
    case UnavailabilityDeleted = 'unavailability_deleted';

    // Phase 11 D5.7.8 audit : Vehicle.exit_date modifie le périmètre
    // taxable (clip des contrats post-clôture). Doit invalider les
    // déclarations de l'année qui contiennent un contrat de ce
    // véhicule. Cf. `VehicleObserver` + `DeclarationInvalidationDetector::flagForVehicle`.
    case VehicleUpdated = 'vehicle_updated';
}
