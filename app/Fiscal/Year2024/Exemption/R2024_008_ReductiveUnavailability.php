<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Models\Unavailability;
use Carbon\CarbonImmutable;

/**
 * R-2024-008 — Indisponibilités fiscalement réductrices.
 *
 * **Sémantique v2.0 (ADR-0014, ADR-0016 anticipée)** : règle souveraine.
 * Avant 04.F, ce filtrage était caché dans `AssignmentReadRepository::loadAnnualCumulRows`
 * (jointure SQL sur `unavailabilities`) — court-circuitant la logique
 * fiscale. La règle redevient une vraie `ExemptionRule` qui itère sur
 * les indispos du véhicule et calcule les jours retirés du numérateur
 * du prorata appliqué par R-2024-002.
 *
 * **Sémantique de calcul** :
 * Un jour d'indisponibilité est réducteur s'il :
 *   1. tombe dans un contrat **taxable** du couple (non LCD au sens de
 *      `R2024_021_ShortTermRental::isShortTermRental()`) ;
 *   2. ET porte un type d'indispo `has_fiscal_impact = true` (V1 :
 *      fourrière uniquement ; ADR-0016 raffinera à 4 cas réducteurs en
 *      04.I — accident, contrôle technique long, etc.).
 *
 * Les jours d'indispo qui tombent dans un contrat LCD sont déjà retirés
 * via R-2024-021 — les compter ici serait un double-décompte.
 *
 * **Source légale** : CIBS art. L. 421-118 (assiette en temps
 * d'utilisation effective) ; doctrine BOFiP § 50, § 60, § 190 (indispos
 * subies). Précision V2 (4 cas) : ADR-0016.
 */
final readonly class R2024_008_ReductiveUnavailability implements ExemptionRule
{
    public function __construct(
        private R2024_021_ShortTermRental $shortTermRental,
    ) {}

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
        $taxableDates = [];
        foreach ($context->contractsForPair as $contract) {
            if ($this->shortTermRental->isShortTermRental($contract)) {
                continue;
            }
            foreach ($contract->expandToDaysInYear($context->fiscalYear) as $date) {
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
                'Indisponibilité réductrice — %d jour%s soustrait%s du numérateur (CIBS L. 421-118, BOFiP § 50/60/190)',
                $reductiveCount,
                $reductiveCount > 1 ? 's' : '',
                $reductiveCount > 1 ? 's' : '',
            ),
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
            // end_date est nullable côté DB (indispo « ouverte ») —
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
