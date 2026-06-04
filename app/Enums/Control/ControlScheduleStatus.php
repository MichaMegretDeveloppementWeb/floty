<?php

declare(strict_types=1);

namespace App\Enums\Control;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Derived (never persisted) status of a control's next échéance for a vehicle
 * (Chantier B / B2), computed by {@see App\Services\Control\ControlScheduleService}
 * relative to "today" and the reminder lead window:
 *   - `Upcoming`      : échéance far ahead.
 *   - `DueSoon`       : within the reminder lead window before the échéance.
 *   - `Overdue`       : échéance passed, control not done.
 *   - `DoneRecently`  : an execution was recorded within the recent window.
 *   - `NotApplicable` : échéance falls after the vehicle left the fleet (ADR-0018).
 */
#[TypeScript]
enum ControlScheduleStatus: string
{
    case Upcoming = 'upcoming';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    case DoneRecently = 'done_recently';
    case NotApplicable = 'not_applicable';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'À venir',
            self::DueSoon => 'Échéance proche',
            self::Overdue => 'En retard',
            self::DoneRecently => 'Fait récemment',
            self::NotApplicable => 'Non applicable',
        };
    }

    /**
     * Badge tone (matches the frontend `BadgeTone`) for this status.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Upcoming => 'slate',
            self::DueSoon => 'amber',
            self::Overdue => 'rose',
            self::DoneRecently => 'emerald',
            self::NotApplicable => 'slate',
        };
    }
}
