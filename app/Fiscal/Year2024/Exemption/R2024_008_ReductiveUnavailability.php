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
use App\Models\VehicleEvent;
use Carbon\CarbonImmutable;

/**
 * R-2024-008 · fiscally reductive unavailabilities.
 *
 * Per ADR-0014 + ADR-0016 rev. 1.1, a sovereign rule. Iterates over
 * the vehicle's unavailabilities and computes the days removed from
 * the R-2024-002 prorata numerator.
 *
 * Computation: an unavailability day is reductive if
 *   1. it falls in a taxable contract of the pair (non-LCD per
 *      {@see R2024_021_ShortTermRental::isShortTermRental()});
 *   2. AND the event has `has_fiscal_impact = true` · denormalised at
 *      write time from the frozen reductive natures of the catalogue
 *      (the three off-road cases, see
 *      {@see App\Support\VehicleEvent\EventNatureCatalog::REDUCTIVE}).
 *
 * Days falling inside an LCD contract are already removed by
 * R-2024-021; counting them here would double-count.
 *
 * Legal basis: CIBS L. 421-96 · "a vehicle immobilised or impounded at
 * the request of public authorities is deemed not assigned to economic
 * purposes". Doctrine BOI-AIS-MOB-10-30-10 details the three reductive
 * cases: § 50 (registration certificate suspension R. 322-6 +
 * post-accident ban L. 327-4 / L. 327-5 Code de la route), § 60
 * (public pound L. 325-1 to L. 325-1-2 Code de la route), § 190
 * (effect on the annual assignment proportion). Nature → fiscal effect
 * mapping in ADR-0016 § 4 rev. 1.1.
 */
final readonly class R2024_008_ReductiveUnavailability implements ExemptionRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function __construct(
        private LcdQualifier $shortTermRental,
    ) {}

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Indisponibilités fiscalement réductrices';
    }

    public function description(): string
    {
        return "Le véhicule immobilisé ou mis en fourrière à la demande des pouvoirs publics est réputé ne pas être affecté à des fins économiques (CIBS L. 421-96). Trois cas réducteurs (BOFiP § 50 et § 60) : fourrière publique (C. route L. 325-1 à L. 325-1-2), suspension du certificat d'immatriculation (C. route R. 322-6), interdiction de circuler post-sinistre (C. route L. 327-4 / L. 327-5). Les jours correspondants sont retirés du numérateur du prorata journalier (BOFiP § 190).";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603053/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 50',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20240710#il_en_ressort_qu_9929',
                'consulted_at' => '2026-05-06',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 60',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20240710#60_1148',
                'consulted_at' => '2026-05-06',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 190',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20240710#180_4641',
                'consulted_at' => '2026-05-06',
            ],
        ];
    }

    public function ruleCode(): string
    {
        return 'R-2024-008';
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

        // Intersect with the pair's taxable contracts (non-LCD).
        // When a `daysWindow` is set (VFC-segmented mode), filter the
        // present days to keep only those inside the current segment.
        // Without this, reductive days would be counted on the full
        // year and subtracted in each segment, leading to over-counting.
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
     * ISO `Y-m-d` dates of the vehicle's reductive unavailabilities,
     * clipped to the fiscal year.
     *
     * @param  list<VehicleEvent>  $vehicleEvents
     * @return list<string>
     */
    private function collectReductiveUnavailableDates(array $vehicleEvents, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);
        $dates = [];

        foreach ($vehicleEvents as $vehicleEvent) {
            if (! $vehicleEvent->has_fiscal_impact) {
                continue;
            }

            $start = $vehicleEvent->start_date->toImmutable();
            // end_date is nullable (open-ended unavailability); clamp
            // to year end.
            $end = $vehicleEvent->end_date !== null
                ? $vehicleEvent->end_date->toImmutable()
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
            body: "Trois types d'indisponibilité réduisent la base taxable : fourrière publique (C. route L. 325-1 à L. 325-1-2), suspension du certificat d'immatriculation (C. route R. 322-6), interdiction de circuler après sinistre (C. route L. 327-4 / L. 327-5). Si un contrat de 91 j chevauche 10 j de fourrière publique, le numérateur taxable passe à 81 j. Les jours d'indispo qui tombent dans un contrat déjà exonéré au titre de la location courte durée ne sont pas comptés deux fois. Les autres types d'indispo (maintenance, contrôle technique, fourrière privée, etc.) restent taxables.",
        );
    }
}
