<?php

declare(strict_types=1);

namespace App\Enums\FiscalDeclaration;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Event types that can invalidate a `generated` fiscal declaration (ADR-0015 § D9 rev. 1.1).
 *
 * Every invalidating mutation detected by Observers stacks an entry in
 * `fiscal_declarations.obsolete_reasons` with a `type` strictly within this enum;
 * any other value is rejected at insertion time.
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

    // Vehicle.exit_date alters the taxable scope (clipping of post-exit contracts).
    case VehicleUpdated = 'vehicle_updated';

    // Manual user-triggered regeneration of an active declaration without a scope mutation.
    // Used by `DiscardDraftDeclarationAction` to reversibly reactivate the predecessor when
    // `obsolete_reasons` contains only `VoluntaryModification` entries.
    case VoluntaryModification = 'voluntary_modification';
}
