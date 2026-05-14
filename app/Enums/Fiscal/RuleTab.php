<?php

declare(strict_types=1);

namespace App\Enums\Fiscal;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Onglet de classement fonctionnel d'une règle fiscale sur la page
 * « Règles de calcul ». Phase 13 D5.12 (complément ADR-0022) · ce
 * concept était initialement porté par `resources/js/data/fiscalRulesContent.ts`
 * (couche TS), désormais migré côté PHP pour conformité doctrine
 * « 1 règle = 1 classe PHP, source de vérité unique ».
 *
 * - `Calcul` · règles qui décrivent comment l'application calcule
 *   la taxe (aiguillage, barèmes, exonérations applicables).
 * - `Cadre` · règles de cadre architectural et de gestion des
 *   événements véhicule (redevable, prorata, indispos, garde-fou
 *   Crit'Air, etc.).
 */
#[TypeScript]
enum RuleTab: string
{
    case Calcul = 'calcul';
    case Cadre = 'cadre';

    /**
     * Ordre d'affichage des sections de cet onglet, conforme à la
     * progression pédagogique pensée pour l'utilisateur métier.
     *
     * @return list<RuleSection>
     */
    public function sectionsOrder(): array
    {
        return match ($this) {
            self::Calcul => [
                RuleSection::Aiguillage,
                RuleSection::Bareme,
                RuleSection::Exoneration,
                RuleSection::ExonerationInactive,
            ],
            self::Cadre => [
                RuleSection::CadreImplicite,
                RuleSection::CadreEvenement,
                RuleSection::CadreInterne,
                RuleSection::CadreDeclaratif,
                RuleSection::TaxeConnexe,
            ],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Calcul => 'Calcul des taxes',
            self::Cadre => 'Cadre & fonctionnement',
        };
    }
}
