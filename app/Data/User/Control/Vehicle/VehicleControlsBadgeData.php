<?php

declare(strict_types=1);

namespace App\Data\User\Control\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lightweight, eager badge payload for the vehicle "Contrôles réglementaires"
 * tab label (Chantier B). Surfaces how many active controls are due so the user
 * sees, without opening the tab, that something needs attention.
 *
 * `dueCount` = controls in DueSoon or Overdue. `overdueCount` = the Overdue
 * subset, which drives the badge tone (rose when any overdue, amber otherwise).
 */
#[TypeScript]
final class VehicleControlsBadgeData extends Data
{
    public function __construct(
        public int $dueCount,
        public int $overdueCount,
    ) {}
}
