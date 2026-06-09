<?php

declare(strict_types=1);

namespace App\Enums\Planning;

/**
 * Mode of the planning PDF export chosen in the export modal.
 *
 *   - Complete: full vehicle row with the weekly usage distribution
 *     (numbers only, no density colour), like the planning table.
 *   - Vehicle: a simplified per-vehicle sheet (main fiscal
 *     characteristics + key amounts), without the weekly grid.
 */
enum PlanningExportMode: string
{
    case Complete = 'complete';
    case Vehicle = 'vehicle';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Données complètes',
            self::Vehicle => 'Données véhicule',
        };
    }
}
