<?php

declare(strict_types=1);

namespace App\Enums\Company;

/**
 * Color assigned to a user company for display (chip, vehicle timeline, heatmap).
 * Constrained to the 8 design system tints (`--color-company-*` in `resources/css/app.css`).
 */
enum CompanyColor: string
{
    case Indigo = 'indigo';
    case Emerald = 'emerald';
    case Amber = 'amber';
    case Rose = 'rose';
    case Violet = 'violet';
    case Teal = 'teal';
    case Orange = 'orange';
    case Cyan = 'cyan';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Indigo => 'Indigo',
            self::Emerald => 'Émeraude',
            self::Amber => 'Ambre',
            self::Rose => 'Rose',
            self::Violet => 'Violet',
            self::Teal => 'Turquoise',
            self::Orange => 'Orange',
            self::Cyan => 'Cyan',
        };
    }
}
