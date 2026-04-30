<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Contracts\AbatementRule;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Fiscal\ValueObjects\ExemptionScope;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Services\Shared\Fiscal\FiscalYearContext;

/**
 * Orchestrateur du moteur fiscal Floty (cf. ADR-0006 § 2 — pipeline
 * fixe en 8 étapes).
 *
 *   1. Récupération du contexte (caractéristiques fiscales courantes)
 *   2. Classifications (méthode CO₂, catégorie polluants)
 *   3. Cumul (alimenté par l'appelant via `contractsForPair` et
 *      `vehicleUnavailabilitiesInYear` dans le PipelineContext)
 *   4. Exonérations (collecte des verdicts ; court-circuit hors scope)
 *   5. Abatements (vide en 2024)
 *   6. Tarification (CO₂ + polluants)
 *   7. Prorata + arrondi (Transversal — R-2024-002 calcule le numérateur
 *      depuis les contrats taxables et applique le prorata)
 *   8. Output structuré (PipelineResult)
 *
 * **Refonte 04.F (ADR-0014)** :
 * Le pipeline ne reçoit plus de cumuls agrégés (`daysAssignedToCompany`,
 * `cumulativeDaysForPair`) — il reçoit la matière brute (les contrats
 * du couple, les indispos du véhicule) et les règles souveraines
 * R-2024-021 et R-2024-008 décident des jours exonérés. R-2024-002
 * (Transversal) calcule le numérateur final et l'écrit dans le contexte.
 */
final class FiscalPipeline
{
    public function __construct(
        private readonly FiscalRuleRegistry $registry,
        private readonly FiscalYearContext $yearContext,
        private readonly VehicleFiscalCharacteristicsReadRepositoryInterface $fiscalCharacteristics,
    ) {}

    public function execute(PipelineContext $context): PipelineResult
    {
        $this->validateInputs($context);

        $rules = $this->registry->rulesForYear($context->fiscalYear);

        // Étape 1 — récupération des caractéristiques fiscales courantes
        $context = $this->loadFiscalCharacteristics($context);

        // Étape 2 — Classifications
        foreach ($this->filterByType($rules, ClassificationRule::class) as $rule) {
            $context = $rule->classify($context);
        }

        // Court-circuit R-2024-004 : véhicule hors champ → toutes les
        // taxes à 0, on saute exonérations / abatements / pricing /
        // transversal.
        if ($context->isFiscallyTaxable === false) {
            return $this->buildResult($context);
        }

        // Étape 4 — Exonérations (collecte des verdicts)
        foreach ($this->filterByType($rules, ExemptionRule::class) as $rule) {
            $verdict = $rule->evaluate($context);
            if ($verdict->isExempt) {
                $context = $context
                    ->withExemptionVerdict($verdict)
                    ->withAppliedRule($rule->ruleCode());
            }
        }

        // Étape 5 — Abatements (vide en 2024 mais déjà cablé)
        foreach ($this->filterByType($rules, AbatementRule::class) as $rule) {
            $context = $rule->abate($context);
        }

        // Étape 6 — Tarification
        foreach ($this->filterByType($rules, PricingRule::class) as $rule) {
            $context = $rule->price($context);
        }

        // Application des verdicts d'exonération **totaux** sur les
        // tarifs (avant prorata) — handicap, électrique, OIG, etc. Les
        // verdicts journaliers (partialDays, scope null) ne neutralisent
        // pas les tariffs : ils n'agissent que sur le numérateur dans
        // R-2024-002.
        $context = $this->applyExemptionsToTariffs($context);

        // Étape 7 — Transversales (prorata + arrondi)
        // R-2024-002 calcule daysAssignedToCompany depuis contractsForPair
        // et soustrait les verdicts partialDays.
        foreach ($this->filterByType($rules, TransversalRule::class) as $rule) {
            $context = $rule->apply($context);
        }

        // Étape 8 — Sortie structurée
        return $this->buildResult($context);
    }

    private function validateInputs(PipelineContext $context): void
    {
        if (! $this->yearContext->isSupported($context->fiscalYear)) {
            throw FiscalCalculationException::yearNotSupported($context->fiscalYear);
        }
        // daysAssignedToCompany et cumulativeDaysForPair sont nullable
        // (calculés par R-2024-002). Aucune validation amont possible.
    }

    private function loadFiscalCharacteristics(PipelineContext $context): PipelineContext
    {
        if ($context->currentFiscalCharacteristics !== null) {
            return $context;
        }

        $vfc = $this->fiscalCharacteristics->findCurrentForVehicle($context->vehicle);
        if ($vfc === null) {
            throw FiscalCalculationException::missingFiscalCharacteristics($context->vehicle->id);
        }

        return $context->withCurrentFiscalCharacteristics($vfc);
    }

    /**
     * @template T of object
     *
     * @param  list<object>  $rules
     * @param  class-string<T>  $type
     * @return list<T>
     */
    private function filterByType(array $rules, string $type): array
    {
        return array_values(array_filter(
            $rules,
            static fn (object $rule): bool => $rule instanceof $type,
        ));
    }

    private function applyExemptionsToTariffs(PipelineContext $context): PipelineContext
    {
        $verdicts = $context->exemptionVerdicts;
        if ($verdicts === []) {
            return $context;
        }

        $hasZeroingTariffs = false;
        $covers = [];
        foreach ($verdicts as $verdict) {
            // Verdicts journaliers (partialDays) → scope null → ne
            // neutralisent pas les tariffs (effet via numérateur R-2024-002).
            if ($verdict->scope === null) {
                continue;
            }
            if ($verdict->zeroesFullYearTariffs) {
                $hasZeroingTariffs = true;
            }
            $covers[] = $verdict->scope;
        }

        if ($hasZeroingTariffs) {
            return $context
                ->withCo2FullYearTariff(0.0)
                ->withPollutantsFullYearTariff(0.0);
        }

        $coversBoth = in_array(ExemptionScope::Both, $covers, true);
        $coversCo2 = $coversBoth || in_array(ExemptionScope::Co2Only, $covers, true);
        $coversPollutants = $coversBoth || in_array(ExemptionScope::PollutantsOnly, $covers, true);

        if ($coversCo2) {
            $context = $context->withCo2FullYearTariff(0.0);
        }
        if ($coversPollutants) {
            $context = $context->withPollutantsFullYearTariff(0.0);
        }

        return $context;
    }

    private function buildResult(PipelineContext $context): PipelineResult
    {
        $verdicts = $context->exemptionVerdicts;

        // Cas spécial R-2024-004 : véhicule hors champ fiscal — pas de
        // verdict d'exonération (pipeline court-circuité avant la phase
        // exonérations) mais on doit exposer un motif explicatif sinon
        // l'utilisateur voit « voir motif ci-dessous » sans liste. Le
        // motif précis selon la branche d'exclusion est posé par
        // R-2024-004 elle-même via `withFiscallyTaxableReason()`. Le
        // fallback en chaîne dur-codée ne sert que de filet de sécurité.
        if ($context->isFiscallyTaxable === false) {
            $appliedExemptions = [new AppliedExemption(
                reason: $context->isFiscallyTaxableReason
                    ?? 'Véhicule hors du champ fiscal des taxes annuelles (CIBS L. 421-2).',
                ruleCode: 'R-2024-004',
            )];
        } else {
            $appliedExemptions = array_values(array_filter(array_map(
                static fn (ExemptionVerdict $v): ?AppliedExemption => $v->reason !== null && $v->ruleCode !== null
                    ? new AppliedExemption($v->reason, $v->ruleCode)
                    : null,
                $verdicts,
            )));
        }

        $handicapExempt = false;
        $electricExempt = false;
        $lcdExempt = false;
        foreach ($verdicts as $verdict) {
            if ($verdict->zeroesFullYearTariffs) {
                $handicapExempt = true;
            }
            if ($verdict->scope === ExemptionScope::Co2Only) {
                $electricExempt = true;
            }
            // LCD : marqueur présent dès qu'un contrat du couple est
            // qualifié LCD (R-2024-021 a posé un verdict partialDays).
            // Avec la sémantique per-contract, c'est désormais possible
            // d'avoir un mix LCD/LLD sur le même couple — le bool
            // signale juste qu'il y a au moins un contrat LCD.
            if ($verdict->exemptDaysCount !== null && $verdict->exemptDaysCount > 0) {
                $lcdExempt = true;
            }
        }

        // Valeurs RAW : ce que R-2024-002 (DailyProrata) a posé sur le
        // contexte, **avant** arrondi par couple. Servent à
        // l'agrégation par redevable dans `FleetFiscalAggregator`
        // (R-2024-003 sémantique BOFiP : un seul arrondi par
        // entreprise).
        $co2DueRaw = $context->co2Due ?? 0.0;
        $pollutantsDueRaw = $context->pollutantsDue ?? 0.0;

        // Valeurs ARRONDIES : pour l'affichage par ligne du PDF /
        // drawer planning. Sémantique 1.8 préservée pour les
        // consommateurs existants (`FiscalCalculator::calculate()`).
        $co2Due = round($co2DueRaw, 2, PHP_ROUND_HALF_UP);
        $pollutantsDue = round($pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);
        $totalDue = round($co2Due + $pollutantsDue, 2, PHP_ROUND_HALF_UP);

        return new PipelineResult(
            daysAssigned: $context->daysAssignedToCompany ?? 0,
            cumulativeDaysForPair: $context->cumulativeDaysForPair ?? 0,
            daysInYear: $context->daysInYear,
            lcdExempt: $lcdExempt,
            electricExempt: $electricExempt,
            handicapExempt: $handicapExempt,
            co2Method: $context->resolvedCo2Method ?? HomologationMethod::Pa,
            co2FullYearTariff: $context->co2FullYearTariff ?? 0.0,
            co2Due: $co2Due,
            co2DueRaw: $co2DueRaw,
            pollutantCategory: $context->resolvedPollutantCategory ?? PollutantCategory::MostPolluting,
            pollutantsFullYearTariff: $context->pollutantsFullYearTariff ?? 0.0,
            pollutantsDue: $pollutantsDue,
            pollutantsDueRaw: $pollutantsDueRaw,
            totalDue: $totalDue,
            appliedExemptions: $appliedExemptions,
            appliedRuleCodes: $context->appliedRuleCodes,
        );
    }
}
