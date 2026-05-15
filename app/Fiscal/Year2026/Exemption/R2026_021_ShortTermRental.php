<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Exemption;

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
 * R-2026-021 · Exonération Location de Courte Durée (LCD) · reconduction
 * stricte 2025 · textes CIBS L. 421-129 / L. 421-141 inchangés depuis
 * 01/01/2022, audit Chrome live 15/05/2026 confirme stabilité au
 * 01/09/2026 (non touchés par Ordo 2025-1247).
 *
 * Un contrat de location est qualifié de courte durée si **l'une** des
 * conditions suivantes est vérifiée ·
 *   - durée du contrat ≤ 30 jours consécutifs (`end - start + 1`)
 *   - OU le contrat couvre exactement un mois civil entier (premier au
 *     dernier jour d'un même mois calendaire)
 *
 * Tous les jours d'un contrat LCD sont exonérés des deux taxes (CO₂ +
 * polluants) · retirés du numérateur du prorata appliqué par R-2026-002.
 * La qualification s'apprécie **par contrat individuel** (ADR-0014).
 *
 * **Bug fix L. 421-140/141 verrouillé** · l'URL pour L. 421-141 (LCD
 * polluants miroir) est `LEGIARTI000044602919` (et non 921 · cf. commit
 * 86888246 du 15/05/2026 sur 2024/2025). LEGIARTI000044602921 est
 * L. 421-140 (loueur, R-2026-020 informative).
 */
final readonly class R2026_021_ShortTermRental implements ExemptionRule, LcdQualifier
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public const int THRESHOLD_DAYS = 30;

    public function ruleCode(): string
    {
        return 'R-2026-021';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Exonération LCD (location de courte durée)';
    }

    public function description(): string
    {
        return "Location de courte durée : durée d'un contrat ≤ 30 jours consécutifs OU contrat couvrant exactement un mois civil entier → tous les jours du contrat sont retirés du numérateur du prorata. La qualification s'apprécie par contrat individuel, pas en cumul annuel. Texte identique pour les deux taxes (L. 421-141 reprend L. 421-129 mot pour mot). Reconduction stricte 2025.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602957/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-141',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602919/2026-01-01',
                'consulted_at' => '2026-05-15',
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
     */
    public function isShortTermRental(Contract $contract): bool
    {
        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

        $duration = $start->diffInDays($end) + 1;
        if ($duration <= self::THRESHOLD_DAYS) {
            return true;
        }

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
            body: "Qualification appréciée **par contrat individuel**, pas en cumul annuel par couple. Un contrat est LCD si l'une des deux conditions est vérifiée : durée ≤ 30 jours consécutifs, OU contrat couvrant exactement un mois civil entier (1er → dernier jour du même mois). Tous les jours d'un contrat LCD sont retirés du numérateur du prorata. Reconduction stricte 2025.",
            appliesWhen: 'Pour chaque contrat individuel : durée ≤ 30 jours consécutifs OU contrat = mois civil entier.',
            effect: 'Les jours du contrat LCD sont soustraits du numérateur du prorata appliqué à ce couple (véhicule, entreprise). Si tous les contrats du couple sont LCD, daysAssignedToCompany = 0 → taxe CO₂ + polluants = 0 €.',
            example: 'Contrat A 1er→15 mars (15 j ≤ 30) → exonéré. Contrat B 1er→31 janvier (31 j mais 1 mois civil entier) → exonéré aussi. Contrat C 15 jan→15 mars (60 j à cheval, ni ≤ 30 ni mois civil entier) → taxable au prorata.',
        );
    }
}
