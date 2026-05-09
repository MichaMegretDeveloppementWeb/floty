<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Unavailability\UnavailabilityType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Models\Unavailability;
use Carbon\CarbonImmutable;

/**
 * R-2024-008 - Indisponibilités fiscalement réductrices.
 *
 * **Sémantique v2.1 (ADR-0014 + ADR-0016 rev. 1.1, chantier F)** : règle
 * souveraine. Itère sur les indispos du véhicule et calcule les jours
 * retirés du numérateur du prorata appliqué par R-2024-002.
 *
 * **Sémantique de calcul** :
 * Un jour d'indisponibilité est réducteur s'il :
 *   1. tombe dans un contrat **taxable** du couple (non LCD au sens de
 *      `R2024_021_ShortTermRental::isShortTermRental()`) ;
 *   2. ET porte un type d'indispo `has_fiscal_impact = true` - soit
 *      l'un des 3 cases réducteurs définis par
 *      {@see UnavailabilityType::isFiscallyReductive()} :
 *      `pound_public`, `accident_no_circulation`, `ci_suspension`.
 *
 * Les jours d'indispo qui tombent dans un contrat LCD sont déjà retirés
 * via R-2024-021 - les compter ici serait un double-décompte.
 *
 * **Source légale** : CIBS art. L. 421-96 — « le véhicule immobilisé ou
 * mis en fourrière à la demande des pouvoirs publics est réputé ne pas
 * être affecté à des fins économiques ». La doctrine BOI-AIS-MOB-10-30-10
 * détaille les 3 cas réducteurs : § 50 (suspension du certificat
 * d'immatriculation R. 322-6 + interdiction post-sinistre L. 327-4 / L. 327-5
 * du C. route), § 60 (fourrière publique L. 325-1 à L. 325-1-2 du C. route),
 * § 190 (effet sur la proportion annuelle d'affectation).
 * Mapping enum → effet fiscal : ADR-0016 § 4 rev. 1.1.
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
            ['type' => 'CIBS', 'article' => 'L. 421-96'],
            ['type' => 'BOFIP', 'reference' => 'BOI-AIS-MOB-10-30-10', 'paragraph' => '§ 50'],
            ['type' => 'BOFIP', 'reference' => 'BOI-AIS-MOB-10-30-10', 'paragraph' => '§ 60'],
            ['type' => 'BOFIP', 'reference' => 'BOI-AIS-MOB-10-30-10', 'paragraph' => '§ 190'],
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
        // Si une `daysWindow` est posée (mode segmenté par VFC, cf.
        // FiscalSegmentedExecutor), on filtre les jours présents
        // pour ne compter que ceux qui tombent dans le segment courant
        // — sinon le count des jours réducteurs serait calculé sur
        // l'année entière et soustrait à chaque segment, conduisant
        // à un sur-décompte (chantier dette VFC, garantie cohérence
        // multi-VFC + indispo réductrice).
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
     * Liste des dates ISO (Y-m-d) des indispos fiscalement réductrices
     * du véhicule clampées à l'année fiscale.
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

            $start = CarbonImmutable::parse($unavailability->start_date->toDateString());
            // end_date est nullable côté DB (indispo « ouverte ») -
            // dans ce cas, on clamp à fin d'année.
            $end = $unavailability->end_date !== null
                ? CarbonImmutable::parse($unavailability->end_date->toDateString())
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
}
