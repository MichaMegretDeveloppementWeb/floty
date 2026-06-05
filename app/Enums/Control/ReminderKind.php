<?php

declare(strict_types=1);

namespace App\Enums\Control;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Kind of reminder occurrence that fired for a control on a given day
 * (Chantier B / B3):
 *   - `Before` : the lead reminder, `days_before` days before the échéance.
 *   - `OnDue`  : on the échéance day itself (when enabled).
 *   - `After`  : a post-échéance repeat (every `repeat_every_days`) while the
 *     control is not yet done.
 */
#[TypeScript]
enum ReminderKind: string
{
    case Before = 'before';
    case OnDue = 'on_due';
    case After = 'after';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Before => 'Avant échéance',
            self::OnDue => 'Jour J',
            self::After => 'En retard',
        };
    }
}
