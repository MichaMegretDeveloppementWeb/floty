<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-021 — Exonération LCD ≤ 30 jours cumulés par couple (CIBS
 * L. 421-129 / L. 421-141).
 *
 * Si le cumul annuel d'affectation du couple (véhicule, entreprise
 * utilisatrice) reste ≤ 30 jours, l'entreprise est exonérée des deux
 * taxes pour ce véhicule sur l'année.
 *
 * Sémantique préservée : les tarifs annuels pleins **restent visibles**
 * dans le breakdown (UX informative — l'utilisateur voit ce qu'il
 * paierait au-delà du seuil).
 */
final readonly class R2024_021_LowDayCount implements ExemptionRule
{
    public const int THRESHOLD_DAYS = 30;

    public function ruleCode(): string
    {
        return 'R-2024-021';
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
        if ($context->cumulativeDaysForPair > self::THRESHOLD_DAYS) {
            return ExemptionVerdict::notExempt();
        }

        return ExemptionVerdict::full(sprintf(
            'Exonération LCD — cumul annuel %d j ≤ %d j (CIBS L. 421-129 / L. 421-141)',
            $context->cumulativeDaysForPair,
            self::THRESHOLD_DAYS,
        ));
    }
}
