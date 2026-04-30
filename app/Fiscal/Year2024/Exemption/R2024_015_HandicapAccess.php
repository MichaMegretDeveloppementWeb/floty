<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-015 — Exonération handicap (CIBS L. 421-123 / L. 421-136).
 *
 * Véhicules accessibles aux personnes à mobilité réduite : exonération
 * **totale** des deux taxes ET les tarifs annuels pleins ne sont PAS
 * affichés dans le breakdown (zeroisés). Sémantique préservée pour
 * compat avec les goldens : on ne montre pas « ce que vous auriez
 * payé » dans ce cas.
 */
final readonly class R2024_015_HandicapAccess implements ExemptionRule
{
    public function ruleCode(): string
    {
        return 'R-2024-015';
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
