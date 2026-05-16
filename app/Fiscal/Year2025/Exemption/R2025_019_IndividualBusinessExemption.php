<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2025-019 · Exonération entreprise individuelle (CIBS L. 421-127
 * / L. 421-139) · reconduction stricte R-2024-019 INACTIVE par défaut.
 *
 * Si l'entreprise utilisatrice est une personne physique exerçant en
 * son nom propre (entrepreneur individuel BIC/BNC), l'exonération
 * s'applique sur les deux taxes (sous conditions de minimis).
 *
 * **Inactif par défaut V1** · aucune entreprise utilisatrice Floty
 * actuelle n'est en nom propre.
 */
final readonly class R2025_019_IndividualBusinessExemption implements ExemptionRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2025-019';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Exonération entreprise individuelle';
    }

    public function description(): string
    {
        return 'Personne physique exerçant son activité professionnelle en nom propre (entrepreneur individuel BIC/BNC) : exonération soumise aux conditions de minimis. Texte identique pour les deux taxes (L. 421-139 reprend L. 421-127 mot pour mot). INACTIVE par défaut. Reconduction stricte 2024.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 19;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-127',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602963/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
        ];
    }

    public function isActive(): bool
    {
        return false;
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        return ExemptionVerdict::notExempt();
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::ExonerationInactive,
            title: 'Exonération entreprise individuelle',
            pitch: 'Véhicule affecté par une personne physique exerçant en nom propre (entrepreneur individuel BIC/BNC) · exonération soumise aux conditions du règlement de minimis.',
            body: 'Inactive par défaut : si une entreprise utilisatrice est un entrepreneur individuel (personne physique en nom propre, régime BIC ou BNC), cette exonération devient applicable et peut être activée manuellement. Le bénéfice est subordonné aux conditions du règlement européen général de minimis (ou règlements sectoriels agricole/pêche).',
        );
    }
}
