<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-015 - Exonération handicap (CIBS L. 421-123 / L. 421-136).
 *
 * Véhicules accessibles aux personnes à mobilité réduite : exonération
 * **totale** des deux taxes ET les tarifs annuels pleins ne sont PAS
 * affichés dans le breakdown (zeroisés). Sémantique préservée pour
 * compat avec les goldens : on ne montre pas « ce que vous auriez
 * payé » dans ce cas.
 */
final readonly class R2024_015_HandicapAccess implements ExemptionRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-015';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Exonération handicap';
    }

    public function description(): string
    {
        return 'Véhicules accessibles aux personnes à mobilité réduite : exonération totale CO₂ et polluants. Texte identique pour les deux taxes (L. 421-136 reprend L. 421-123 mot pour mot).';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 15;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            ['type' => 'CIBS', 'article' => 'L. 421-123'],
        ];
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
        if ($context->currentFiscalCharacteristics?->handicap_access === true) {
            return ExemptionVerdict::fullZeroingTariffs(
                'Exonération handicap (CIBS L. 421-123 / L. 421-136)',
                $this->ruleCode(),
            );
        }

        return ExemptionVerdict::notExempt();
    }
}
