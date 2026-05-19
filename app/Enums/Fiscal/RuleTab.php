<?php

declare(strict_types=1);

namespace App\Enums\Fiscal;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Functional tab classification of a fiscal rule on the "Règles de calcul" page (ADR-0022).
 *
 * - `Calcul`: rules that produce or reduce an amount in Floty.
 * - `Cadre`: framework and operational rules (scale routing, daily prorata, unavailabilities).
 * - `HorsPerimetre`: legal vehicle taxes documented but not computed by Floty.
 */
#[TypeScript]
enum RuleTab: string
{
    case Calcul = 'calcul';
    case Cadre = 'cadre';
    case HorsPerimetre = 'hors-perimetre';

    /**
     * Display order of sections within this tab, following the pedagogical progression.
     *
     * @return list<RuleSection>
     */
    public function sectionsOrder(): array
    {
        return match ($this) {
            self::Calcul => [
                RuleSection::Bareme,
                RuleSection::Exoneration,
            ],
            self::Cadre => [
                RuleSection::Aiguillage,
                RuleSection::CadreImplicite,
                RuleSection::CadreEvenement,
                RuleSection::CadreInterne,
                RuleSection::CadreDeclaratif,
            ],
            self::HorsPerimetre => [
                RuleSection::ExonerationInactive,
                RuleSection::TaxeConnexe,
            ],
        };
    }

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Calcul => 'Calcul des taxes',
            self::Cadre => 'Cadre & fonctionnement',
            self::HorsPerimetre => 'Hors périmètre Floty',
        };
    }
}
