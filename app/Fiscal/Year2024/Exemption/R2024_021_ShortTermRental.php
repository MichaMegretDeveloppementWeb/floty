<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Models\Contract;
use Carbon\CarbonImmutable;

/**
 * R-2024-021 - Exonération Location de Courte Durée (LCD).
 *
 * **Sémantique v2.0 (ADR-0014, conforme BOFiP § 180-190)** :
 * Un contrat de location est qualifié de courte durée si **l'une** des
 * conditions suivantes est vérifiée :
 *   - durée du contrat ≤ 30 jours consécutifs (`end - start + 1`)
 *   - **OU** le contrat couvre exactement un mois civil entier
 *     (premier au dernier jour d'un même mois calendaire)
 *
 * Tous les jours d'un contrat LCD sont exonérés des deux taxes (CO₂ +
 * polluants) - ils sont retirés du numérateur du prorata appliqué par
 * R-2024-002. La qualification s'apprécie **par contrat individuel**,
 * jamais en cumul du couple.
 *
 * **Source légale** : CIBS art. L. 421-129 et L. 421-141 (renvoi à la
 * définition « location de courte durée » du Code monétaire et
 * financier) ; doctrine BOFiP-IS-DG-30-10-30.
 *
 * **Architecture** (cf. memory `feedback_fiscal_rules_authority`) : la
 * qualification LCD est portée par cette règle souveraine - aucun
 * service ne décide à sa place. R-2024-008 (indispos réductrices)
 * délègue à `isShortTermRental()` pour distinguer contrats taxables et
 * contrats déjà LCD-exonérés.
 */
final readonly class R2024_021_ShortTermRental implements ExemptionRule
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
        $exemptDays = 0;
        $lcdContractsCount = 0;

        foreach ($context->contractsForPair as $contract) {
            if (! $this->isShortTermRental($contract)) {
                continue;
            }
            $exemptDays += count($contract->expandToDaysInYear($context->fiscalYear));
            $lcdContractsCount++;
        }

        if ($exemptDays === 0) {
            return ExemptionVerdict::notExempt();
        }

        return ExemptionVerdict::partialDays(
            $exemptDays,
            sprintf(
                'Exonération LCD - %d contrat%s court%s (%d jour%s) (CIBS L. 421-129 / L. 421-141, BOFiP § 180-190)',
                $lcdContractsCount,
                $lcdContractsCount > 1 ? 's' : '',
                $lcdContractsCount > 1 ? 's' : '',
                $exemptDays,
                $exemptDays > 1 ? 's' : '',
            ),
            $this->ruleCode(),
        );
    }

    /**
     * Qualification LCD d'un contrat individuel (ADR-0014, BOFiP § 180-190).
     *
     * Public car réutilisée par `R2024_008_ReductiveUnavailability`
     * pour distinguer les contrats taxables des contrats LCD lors du
     * calcul des indispos fiscalement réductrices.
     */
    public function isShortTermRental(Contract $contract): bool
    {
        $start = CarbonImmutable::parse($contract->start_date->toDateString());
        $end = CarbonImmutable::parse($contract->end_date->toDateString());

        $duration = $start->diffInDays($end) + 1;
        if ($duration <= self::THRESHOLD_DAYS) {
            return true;
        }

        // Cas-limite « 1 mois civil entier » : le contrat couvre
        // exactement les jours d'un mois calendaire (ex. 1er → 31
        // janvier = 31 jours, donc > 30, mais c'est un mois civil
        // entier → LCD).
        if (
            $start->day === 1
            && $end->day === $end->daysInMonth
            && $start->month === $end->month
            && $start->year === $end->year
        ) {
            return true;
        }

        return false;
    }
}
