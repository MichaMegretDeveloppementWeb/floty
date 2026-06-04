<?php

declare(strict_types=1);

namespace App\Enums\Control;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Direction of a reminder-recipient delta (Chantier B):
 *   - `Include` : adds a recipient at this level (carries a name + email).
 *   - `Exclude` : removes an inherited recipient, matched by normalised email.
 */
#[TypeScript]
enum RecipientOperation: string
{
    case Include = 'include';
    case Exclude = 'exclude';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Include => 'Ajouté',
            self::Exclude => 'Retiré',
        };
    }
}
