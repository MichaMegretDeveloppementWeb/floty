<?php

declare(strict_types=1);

namespace App\Enums\Control;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-vehicle lifecycle status of a control (Chantier B / B2), stored on
 * {@see App\Models\VehicleControlOverride}:
 *   - `Active`   : the control applies and its reminders run (B3).
 *   - `Paused`   : kept but its reminders are suppressed (e.g. a vehicle set
 *     aside for a while); the échéance is still shown.
 *   - `Disabled` : the (global) control no longer applies to this vehicle.
 */
#[TypeScript]
enum VehicleControlStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Paused => 'En pause',
            self::Disabled => 'Désactivé',
        };
    }
}
