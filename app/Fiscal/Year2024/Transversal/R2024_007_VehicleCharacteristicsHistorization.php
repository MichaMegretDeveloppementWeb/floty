<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-007 · Historisation des caractéristiques véhicule.
 *
 * **Règle documentaire-only** (ADR-0022 · complément Phase 13 D5.11) ·
 * cette règle pose le principe d'historisation des VFC
 * (VehicleFiscalCharacteristics) · à chaque jour d'affectation, la
 * version effective des caractéristiques fiscales du véhicule à cette
 * date est utilisée pour le calcul. C'est l'exécuteur segmenté
 * (FiscalSegmentedExecutor) qui matérialise cette doctrine en
 * découpant le pipeline par sous-période de VFC effective.
 */
final readonly class R2024_007_VehicleCharacteristicsHistorization implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-007';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Historisation des caractéristiques véhicule';
    }

    public function description(): string
    {
        return "Application de la version effective des caractéristiques fiscales à chaque jour d'affectation.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 7;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-164',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214908/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreInterne,
            title: 'Historisation des caractéristiques véhicule',
            pitch: 'Les caractéristiques fiscales d’un véhicule sont datées : une correction de CO₂ prend effet à sa date d’application, sans rétroactivité sur les déclarations passées.',
            body: "Chaque véhicule a un historique de ses caractéristiques fiscales (motorisation, CO₂, norme Euro). Le calcul d'une attribution utilise les caractéristiques effectives à la date de cette attribution. Si une donnée est corrigée (erreur de saisie), l'ancienne valeur reste visible pour audit et reproductibilité des déclarations déjà envoyées.",
        );
    }
}
