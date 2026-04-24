<?php

namespace App\Enums\Company;

/**
 * Couleur affectée à une entreprise utilisatrice pour son affichage
 * (chip, timeline véhicule, heatmap). Contraint aux **8 teintes** du
 * design system Floty — cf. UI Kit `company-chip` et les variables CSS
 * `--color-company-*` de `resources/css/app.css`.
 *
 * Le frontend expose un type TS jumeau `CompanyColor` dans
 * `resources/js/types/ui.ts` — les deux doivent rester synchronisés
 * (le générateur Spatie TS Transformer assurera cette synchro dès
 * que cet enum apparaît dans un Data DTO, phase 05).
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
