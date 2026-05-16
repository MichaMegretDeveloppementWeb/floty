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
 * Refonte 2026-05-16 (mockup A) · la dimension « ce que Floty calcule
 * vs ce qui existe en droit mais que Floty n'applique pas » est portée
 * par le 3e onglet (`HorsPerimetre`), qui regroupe désormais aussi les
 * exonérations légales non appliquées par l'app. Aiguillage devient
 * une étape de cadre (sélection du barème), pas un calcul.
 *
 * - `Calcul` · règles qui produisent un montant ou le réduisent dans
 *   Floty (barèmes annuels + exonérations actives).
 * - `Cadre` · règles de fonctionnement de l'application · choix du
 *   barème selon le véhicule, prorata journalier, traitement des
 *   indisponibilités et événements véhicule, modalités déclaratives.
 * - `HorsPerimetre` · règles fiscales véhicules **prévues par la loi
 *   mais non appliquées dans Floty** · exonérations sans cas d'usage
 *   (OIG, entreprise individuelle, activités spécifiques) et taxes
 *   connexes (malus immat, carte grise, TAI, taxe véhicules lourds)
 *   acquittées par d'autres acteurs.
 */
#[TypeScript]
enum RuleTab: string
{
    case Calcul = 'calcul';
    case Cadre = 'cadre';
    case HorsPerimetre = 'hors-perimetre';

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

    public function label(): string
    {
        return match ($this) {
            self::Calcul => 'Calcul des taxes',
            self::Cadre => 'Cadre & fonctionnement',
            self::HorsPerimetre => 'Hors périmètre Floty',
        };
    }
}
