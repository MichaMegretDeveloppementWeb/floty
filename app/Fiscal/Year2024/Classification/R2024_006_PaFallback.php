<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;

/**
 * R-2024-006 · Bascule sur barème PA (CO₂ manquant).
 *
 * **Règle documentaire-only** (ADR-0022 · complément Phase 13 D5.11) ·
 * cette règle décrit la doctrine de fallback fiscale · lorsque la
 * donnée d'émissions CO₂ est manquante sur la VFC, le barème
 * Puissance Administrative (PA) est appliqué à la place du barème
 * WLTP/NEDC. La sélection effective de méthode est faite par la
 * règle pipeline {@see App\Fiscal\Year2024\Classification\R2024_005_Co2MethodSelection}.
 */
final readonly class R2024_006_PaFallback implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-006';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Bascule sur barème PA (CO₂ manquant)';
    }

    public function description(): string
    {
        return 'Fallback vers le barème Puissance Administrative si la donnée CO₂ est manquante.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
        return 6;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-119-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048802414/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }
}
