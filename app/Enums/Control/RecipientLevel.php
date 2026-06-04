<?php

declare(strict_types=1);

namespace App\Enums\Control;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Level at which a reminder-recipient delta applies, in cascade order
 * (Chantier B). Reminder recipients are never materialised: the effective
 * list is resolved on the fly by applying include/exclude deltas level by
 * level (settings, then definition, then vehicle).
 *
 *   - `Settings`   : level 0, the global default recipients.
 *   - `Definition` : level 1, a global control definition's own additions /
 *     removals over the defaults.
 *   - `Vehicle`    : level 2, a per-vehicle override (introduced in B2).
 */
#[TypeScript]
enum RecipientLevel: string
{
    case Settings = 'settings';
    case Definition = 'definition';
    case Vehicle = 'vehicle';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Settings => 'Paramètres généraux',
            self::Definition => 'Contrôle',
            self::Vehicle => 'Véhicule',
        };
    }
}
