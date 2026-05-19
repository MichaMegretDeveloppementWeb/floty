<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Side-effect category triggered by a VFC edit on its neighbours in the vehicle history.
 *
 * Produced by `FiscalCharacteristicsImpactComputer`, consumed by `UpdateFiscalCharacteristicsAction`.
 * `Delete` is destructive and requires explicit user confirmation.
 */
enum FiscalCharacteristicsImpactType: string
{
    /** Another VFC is fully absorbed by the new range and must be deleted. */
    case Delete = 'delete';

    /** A VFC's `effective_to` must be adjusted to stay consistent with the new bounds. */
    case AdjustEffectiveTo = 'adjust_effective_to';

    /** A VFC's `effective_from` must be adjusted to stay consistent with the new bounds. */
    case AdjustEffectiveFrom = 'adjust_effective_from';
}
