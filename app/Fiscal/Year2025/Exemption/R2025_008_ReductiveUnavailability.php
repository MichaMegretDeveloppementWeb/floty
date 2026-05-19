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
use App\Models\Unavailability;
use Carbon\CarbonImmutable;

/**
 * R-2025-008 - Fiscally reductive unavailabilities, strict reproduction
 * of R-2024-008 (ADR-0016 confirmed for 2025 by BOFiP
 * `BOI-AIS-MOB-10-30-10-20250528` §§ 50, 60, 190).
 *
 * Unchanged ADR-0016 grid: 4 reductive cases (public impound L. 325-1
 * to L. 325-1-2, registration suspension R. 322-6, post-incident
 * circulation ban L. 327-4 / L. 327-5, end-of-life certificate
 * R. 322-9).
 *
 * Calculation semantics identical to 2024: a day is reductive if it
 * falls inside a taxable contract of the pair (not LCD per R-2025-021)
 * AND carries an unavailability type with `has_fiscal_impact = true`.
 */
final readonly class R2025_008_ReductiveUnavailability implements ExemptionRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function __construct(
        private LcdQualifier $shortTermRental,
    ) {}

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Indisponibilités fiscalement réductrices';
    }

    public function description(): string
    {
        return "Le véhicule immobilisé ou mis en fourrière à la demande des pouvoirs publics est réputé ne pas être affecté à des fins économiques (CIBS L. 421-96). Quatre cas réducteurs (BOFiP §§ 50 et 60) : fourrière publique (C. route L. 325-1 à L. 325-1-2), suspension du certificat d'immatriculation (C. route R. 322-6), interdiction de circuler post-sinistre (C. route L. 327-4 / L. 327-5), certificat de destruction VHU (C. route R. 322-9). Les jours correspondants sont retirés du numérateur du prorata journalier (BOFiP § 190). Reconduction stricte 2024.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 8;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-96',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603053/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 50',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 60',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 190',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528',
                'consulted_at' => '2026-05-14',
            ],
        ];
    }

    public function ruleCode(): string
    {
        return 'R-2025-008';
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
        $reductiveDates = $this->collectReductiveUnavailableDates(
            $context->vehicleUnavailabilitiesInYear,
            $context->fiscalYear,
        );

        if ($reductiveDates === []) {
            return ExemptionVerdict::notExempt();
        }

        $window = $context->daysWindow;
        $taxableDates = [];
        foreach ($context->contractsForPair as $contract) {
            if ($this->shortTermRental->isShortTermRental($contract)) {
                continue;
            }
            foreach ($contract->expandToDaysInYear($context->fiscalYear) as $date) {
                if ($window !== null && ! $window->contains(CarbonImmutable::parse($date))) {
                    continue;
                }
                $taxableDates[$date] = true;
            }
        }

        $reductiveCount = 0;
        foreach ($reductiveDates as $date) {
            if (isset($taxableDates[$date])) {
                $reductiveCount++;
            }
        }

        if ($reductiveCount === 0) {
            return ExemptionVerdict::notExempt();
        }

        return ExemptionVerdict::partialDays(
            $reductiveCount,
            sprintf(
                'Indisponibilité réductrice - %d jour%s soustrait%s du numérateur (CIBS L. 421-96, BOFiP BOI-AIS-MOB-10-30-10 § 50/60/190)',
                $reductiveCount,
                $reductiveCount > 1 ? 's' : '',
                $reductiveCount > 1 ? 's' : '',
            ),
            $this->ruleCode(),
        );
    }

    /**
     * @param  list<Unavailability>  $unavailabilities
     * @return list<string>
     */
    private function collectReductiveUnavailableDates(array $unavailabilities, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);
        $dates = [];

        foreach ($unavailabilities as $unavailability) {
            if (! $unavailability->has_fiscal_impact) {
                continue;
            }

            $start = $unavailability->start_date->toImmutable();
            $end = $unavailability->end_date !== null
                ? $unavailability->end_date->toImmutable()
                : $yearEnd;

            $rangeStart = $start->isAfter($yearStart) ? $start : $yearStart;
            $rangeEnd = $end->isBefore($yearEnd) ? $end : $yearEnd;
            if ($rangeStart->isAfter($rangeEnd)) {
                continue;
            }

            $cursor = $rangeStart;
            while (! $cursor->isAfter($rangeEnd)) {
                $dates[$cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        }

        return array_keys($dates);
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreEvenement,
            title: 'Indisponibilités fiscalement réductrices',
            pitch: 'Les jours pendant lesquels le véhicule est immobilisé ou mis en fourrière à la demande des pouvoirs publics sont retirés du numérateur du prorata.',
            body: "Quatre types d'indisponibilité réduisent la base taxable : fourrière publique (C. route L. 325-1 à L. 325-1-2), suspension du certificat d'immatriculation (C. route R. 322-6), interdiction de circuler après sinistre (C. route L. 327-4 / L. 327-5), certificat de destruction VHU (R. 322-9). Si un contrat de 91 j chevauche 10 j de fourrière publique, le numérateur taxable passe à 81 j. Les jours d'indispo qui tombent dans un contrat déjà exonéré au titre de la location courte durée ne sont pas comptés deux fois. Reconduction stricte 2024 (BOFiP 2025 confirme intégralement).",
        );
    }
}
