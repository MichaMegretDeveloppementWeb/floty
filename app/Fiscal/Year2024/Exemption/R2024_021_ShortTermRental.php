<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Models\Contract;

/**
 * R-2024-021 · short-term rental (LCD) exemption.
 *
 * Per ADR-0014 and BOFiP § 180-190, a rental contract is short-term
 * iff one of the following holds:
 *   - contract duration ≤ 30 consecutive days (`end - start + 1`)
 *   - OR contract covers exactly one full civil month (first to last
 *     day of the same calendar month)
 *
 * Every day of an LCD contract is exempt from both taxes (CO₂ +
 * pollutants) and removed from the prorata numerator handled by
 * R-2024-002. Qualification is per individual contract, never on the
 * pair's cumulative duration.
 *
 * Legal basis: CIBS art. L. 421-129 and L. 421-141 (referring to the
 * "short-term rental" definition from the Code monétaire et
 * financier); doctrine BOFiP-IS-DG-30-10-30.
 *
 * Architecture: LCD qualification is owned by this sovereign rule, no
 * service decides instead. R-2024-008 (reductive unavailabilities)
 * delegates to `isShortTermRental()` to distinguish taxable contracts
 * from already-LCD-exempt contracts.
 */
final readonly class R2024_021_ShortTermRental implements ExemptionRule, LcdQualifier
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public const int THRESHOLD_DAYS = 30;

    public function ruleCode(): string
    {
        return 'R-2024-021';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Exonération LCD (location de courte durée)';
    }

    public function description(): string
    {
        return "Location de courte durée : durée d'un contrat ≤ 30 jours consécutifs OU contrat couvrant exactement un mois civil entier → tous les jours du contrat sont retirés du numérateur du prorata. La qualification s'apprécie par contrat individuel, pas en cumul annuel. Texte identique pour les deux taxes (L. 421-141 reprend L. 421-129 mot pour mot).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 21;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-129',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602957/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-141',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602919/2024-06-01',
                'consulted_at' => '2026-05-14',
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

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        $exemptDays = 0;
        $lcdContractsCount = 0;

        foreach ($context->contractsForPair as $contract) {
            if (! $this->isShortTermRental($contract)) {
                continue;
            }
            $exemptDays += $contract->countDaysInYear($context->fiscalYear);
            $lcdContractsCount++;
        }

        if ($exemptDays === 0) {
            return ExemptionVerdict::notExempt();
        }

        return ExemptionVerdict::partialDays(
            $exemptDays,
            sprintf(
                'Exonération LCD - %d location%s courte%s (%d jour%s) (CIBS L. 421-129 / L. 421-141, BOFiP § 180-190)',
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
        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Exoneration,
            title: 'Exonération location de courte durée (LCD)',
            pitch: 'Un contrat de location de 30 jours ou moins (ou couvrant exactement un mois civil entier) est totalement exonéré : ses jours sortent du calcul.',
            body: "Qualification appréciée **par contrat individuel**, pas en cumul annuel par couple. Un contrat est LCD si l'une des deux conditions est vérifiée : durée ≤ 30 jours consécutifs, OU contrat couvrant exactement un mois civil entier (1er → dernier jour du même mois). Tous les jours d'un contrat LCD sont retirés du numérateur du prorata.",
            appliesWhen: 'Pour chaque contrat individuel : durée ≤ 30 jours consécutifs OU contrat = mois civil entier.',
            effect: 'Les jours du contrat LCD sont soustraits du numérateur du prorata appliqué à ce couple (véhicule, entreprise). Si tous les contrats du couple sont LCD, daysAssignedToCompany = 0 → taxe CO₂ + polluants = 0 €.',
            example: 'Contrat A 1er→15 mars (15 j ≤ 30) → exonéré. Contrat B 1er→31 janvier (31 j mais 1 mois civil entier) → exonéré aussi. Contrat C 15 jan→15 mars (60 j à cheval, ni ≤ 30 ni mois civil entier) → taxable au prorata.',
        );
    }
}
