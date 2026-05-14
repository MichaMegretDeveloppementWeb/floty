<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Unavailability\UnavailabilityType;
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
 * R-2024-008 - IndisponibilitÃ©s fiscalement rÃ©ductrices.
 *
 * **SÃ©mantique v2.1 (ADR-0014 + ADR-0016 rev. 1.1, chantier F)** : rÃ¨gle
 * souveraine. ItÃ¨re sur les indispos du vÃ©hicule et calcule les jours
 * retirÃ©s du numÃ©rateur du prorata appliquÃ© par R-2024-002.
 *
 * **SÃ©mantique de calcul** :
 * Un jour d'indisponibilitÃ© est rÃ©ducteur s'il :
 *   1. tombe dans un contrat **taxable** du couple (non LCD au sens de
 *      `R2024_021_ShortTermRental::isShortTermRental()`) ;
 *   2. ET porte un type d'indispo `has_fiscal_impact = true` - soit
 *      l'un des 3 cases rÃ©ducteurs dÃ©finis par
 *      {@see UnavailabilityType::isFiscallyReductive()} :
 *      `pound_public`, `accident_no_circulation`, `ci_suspension`.
 *
 * Les jours d'indispo qui tombent dans un contrat LCD sont dÃ©jÃ  retirÃ©s
 * via R-2024-021 - les compter ici serait un double-dÃ©compte.
 *
 * **Source lÃ©gale** : CIBS art. L. 421-96 Â· Â« le vÃ©hicule immobilisÃ© ou
 * mis en fourriÃ¨re Ã  la demande des pouvoirs publics est rÃ©putÃ© ne pas
 * Ãªtre affectÃ© Ã  des fins Ã©conomiques Â». La doctrine BOI-AIS-MOB-10-30-10
 * dÃ©taille les 3 cas rÃ©ducteurs : Â§ 50 (suspension du certificat
 * d'immatriculation R. 322-6 + interdiction post-sinistre L. 327-4 / L. 327-5
 * du C. route), Â§ 60 (fourriÃ¨re publique L. 325-1 Ã  L. 325-1-2 du C. route),
 * Â§ 190 (effet sur la proportion annuelle d'affectation).
 * Mapping enum â†’ effet fiscal : ADR-0016 Â§ 4 rev. 1.1.
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
        return 'IndisponibilitÃ©s fiscalement rÃ©ductrices';
    }

    public function description(): string
    {
        return "Le vÃ©hicule immobilisÃ© ou mis en fourriÃ¨re Ã  la demande des pouvoirs publics est rÃ©putÃ© ne pas Ãªtre affectÃ© Ã  des fins Ã©conomiques (CIBS L. 421-96). Trois cas rÃ©ducteurs (BOFiP Â§ 50 et Â§ 60) : fourriÃ¨re publique (C. route L. 325-1 Ã  L. 325-1-2), suspension du certificat d'immatriculation (C. route R. 322-6), interdiction de circuler post-sinistre (C. route L. 327-4 / L. 327-5). Les jours correspondants sont retirÃ©s du numÃ©rateur du prorata journalier (BOFiP Â§ 190).";
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
                'paragraph' => 'Â§ 50',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20240710#il_en_ressort_qu_9929',
                'consulted_at' => '2026-05-06',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => 'Â§ 60',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20240710#60_1148',
                'consulted_at' => '2026-05-06',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => 'Â§ 190',
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

        // Intersection avec les jours des contrats taxables du couple
        // (= les contrats du couple qui ne sont PAS LCD).
        //
        // Si une `daysWindow` est posÃ©e (mode segmentÃ© par VFC, cf.
        // FiscalSegmentedExecutor), on filtre les jours prÃ©sents
        // pour ne compter que ceux qui tombent dans le segment courant
        // Â· sinon le count des jours rÃ©ducteurs serait calculÃ© sur
        // l'annÃ©e entiÃ¨re et soustrait Ã  chaque segment, conduisant
        // Ã  un sur-dÃ©compte (chantier dette VFC, garantie cohÃ©rence
        // multi-VFC + indispo rÃ©ductrice).
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
                'IndisponibilitÃ© rÃ©ductrice - %d jour%s soustrait%s du numÃ©rateur (CIBS L. 421-96, BOFiP BOI-AIS-MOB-10-30-10 Â§ 50/60/190)',
                $reductiveCount,
                $reductiveCount > 1 ? 's' : '',
                $reductiveCount > 1 ? 's' : '',
            ),
            $this->ruleCode(),
        );
    }

    /**
     * Liste des dates ISO (Y-m-d) des indispos fiscalement rÃ©ductrices
     * du vÃ©hicule clampÃ©es Ã  l'annÃ©e fiscale.
     *
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
            // end_date est nullable cÃ´tÃ© DB (indispo Â« ouverte Â») -
            // dans ce cas, on clamp Ã  fin d'annÃ©e.
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
            title: 'IndisponibilitÃ©s fiscalement rÃ©ductrices',
            pitch: 'Les jours pendant lesquels le vÃ©hicule est immobilisÃ© ou mis en fourriÃ¨re Ã  la demande des pouvoirs publics sont retirÃ©s du numÃ©rateur du prorata.',
            body: "Trois types d'indisponibilitÃ© rÃ©duisent la base taxable : fourriÃ¨re publique (C. route L. 325-1 Ã  L. 325-1-2), suspension du certificat d'immatriculation (C. route R. 322-6), interdiction de circuler aprÃ¨s sinistre (C. route L. 327-4 / L. 327-5). Si un contrat de 91 j chevauche 10 j de fourriÃ¨re publique, le numÃ©rateur taxable passe Ã  81 j. Les jours d'indispo qui tombent dans un contrat dÃ©jÃ  exonÃ©rÃ© au titre de la location courte durÃ©e ne sont pas comptÃ©s deux fois. Les autres types d'indispo (maintenance, contrÃ´le technique, fourriÃ¨re privÃ©e, etc.) restent taxables.",
        );
    }
}
