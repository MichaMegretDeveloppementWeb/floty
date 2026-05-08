<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-018 - Exonération organisme d'intérêt général (CIBS L. 421-126
 * / L. 421-138).
 *
 * Si l'entreprise utilisatrice est un organisme d'intérêt général
 * (CGI art. 261, 7°) ET que le véhicule est exclusivement affecté à son
 * activité non lucrative, l'exonération s'applique sur les deux taxes.
 * Flag : `companies.is_oig`.
 *
 * **Inactif par défaut V1** : aucune entreprise utilisatrice Floty
 * actuelle n'est OIG. La règle est structurellement câblée pour
 * activation future via seeder/UI.
 *
 * Note V1 : tant que le {@see PipelineContext} ne porte pas la
 * `Company` du couple, cette règle retourne `notExempt()`. Le critère
 * d'affectation exclusive sera évalué côté contrat (V2) - pas sur la
 * VFC, qui ne porte pas la sémantique d'usage par entreprise.
 */
final readonly class R2024_018_OigExemption implements ExemptionRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2024-018';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return "Exonération organisme d'intérêt général";
    }

    public function description(): string
    {
        return 'OIG (CGI art. 261, 7°) : exonération CO₂ et polluants. Texte identique pour les deux taxes (L. 421-138 reprend L. 421-126 mot pour mot). INACTIVE par défaut.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 18;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            ['type' => 'CIBS', 'article' => 'L. 421-126'],
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
        // Tant que le contexte ne porte pas la company, pas
        // d'évaluation possible. Cas attendu V1.
        return ExemptionVerdict::notExempt();
    }
}
