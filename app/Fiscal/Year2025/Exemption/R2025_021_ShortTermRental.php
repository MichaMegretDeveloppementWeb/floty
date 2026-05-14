<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Exemption;

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
 * R-2025-021 Â· ExonÃ©ration Location de Courte DurÃ©e (LCD) Â· reconduction
 * stricte R-2024-021 (textes CIBS L. 421-129 / L. 421-141 inchangÃ©s
 * depuis 01/01/2022, conformes BOFiP Â§ 180-190).
 *
 * Un contrat de location est qualifiÃ© de courte durÃ©e si **l'une** des
 * conditions suivantes est vÃ©rifiÃ©e :
 *   - durÃ©e du contrat â‰¤ 30 jours consÃ©cutifs (`end - start + 1`)
 *   - OU le contrat couvre exactement un mois civil entier (premier au
 *     dernier jour d'un mÃªme mois calendaire)
 *
 * Tous les jours d'un contrat LCD sont exonÃ©rÃ©s des deux taxes (COâ‚‚ +
 * polluants) Â· retirÃ©s du numÃ©rateur du prorata appliquÃ© par R-2025-002.
 * La qualification s'apprÃ©cie **par contrat individuel** (ADR-0014).
 */
final readonly class R2025_021_ShortTermRental implements ExemptionRule, LcdQualifier
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public const int THRESHOLD_DAYS = 30;

    public function ruleCode(): string
    {
        return 'R-2025-021';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'ExonÃ©ration LCD (location de courte durÃ©e)';
    }

    public function description(): string
    {
        return "Location de courte durÃ©e : durÃ©e d'un contrat â‰¤ 30 jours consÃ©cutifs OU contrat couvrant exactement un mois civil entier â†’ tous les jours du contrat sont retirÃ©s du numÃ©rateur du prorata. La qualification s'apprÃ©cie par contrat individuel, pas en cumul annuel. Texte identique pour les deux taxes (L. 421-141 reprend L. 421-129 mot pour mot). Reconduction stricte 2024.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602957/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-141',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602919/2025-01-01',
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
            $exemptDays += count($contract->expandToDaysInYear($context->fiscalYear));
            $lcdContractsCount++;
        }

        if ($exemptDays === 0) {
            return ExemptionVerdict::notExempt();
        }

        return ExemptionVerdict::partialDays(
            $exemptDays,
            sprintf(
                'ExonÃ©ration LCD - %d location%s courte%s (%d jour%s) (CIBS L. 421-129 / L. 421-141, BOFiP Â§ 180-190)',
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
     * Qualification LCD d'un contrat individuel (ADR-0014, BOFiP Â§ 180-190).
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
            title: 'ExonÃ©ration location de courte durÃ©e (LCD)',
            pitch: 'Un contrat de location de 30 jours ou moins (ou couvrant exactement un mois civil entier) est totalement exonÃ©rÃ© : ses jours sortent du calcul.',
            body: "Qualification apprÃ©ciÃ©e **par contrat individuel**, pas en cumul annuel par couple. Un contrat est LCD si l'une des deux conditions est vÃ©rifiÃ©e : durÃ©e â‰¤ 30 jours consÃ©cutifs, OU contrat couvrant exactement un mois civil entier (1er â†’ dernier jour du mÃªme mois). Tous les jours d'un contrat LCD sont retirÃ©s du numÃ©rateur du prorata. Reconduction stricte 2024.",
            appliesWhen: 'Pour chaque contrat individuel : durÃ©e â‰¤ 30 jours consÃ©cutifs OU contrat = mois civil entier.',
            effect: 'Les jours du contrat LCD sont soustraits du numÃ©rateur du prorata appliquÃ© Ã  ce couple (vÃ©hicule, entreprise). Si tous les contrats du couple sont LCD, daysAssignedToCompany = 0 â†’ taxe COâ‚‚ + polluants = 0 â‚¬.',
            example: 'Contrat A 1erâ†’15 mars (15 j â‰¤ 30) â†’ exonÃ©rÃ©. Contrat B 1erâ†’31 janvier (31 j mais 1 mois civil entier) â†’ exonÃ©rÃ© aussi. Contrat C 15 janâ†’15 mars (60 j Ã  cheval, ni â‰¤ 30 ni mois civil entier) â†’ taxable au prorata.',
        );
    }
}
