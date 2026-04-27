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
use App\Fiscal\ValueObjects\ExemptionScope;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Services\Shared\Fiscal\FiscalYearContext;

/**
 * Orchestrateur du moteur fiscal Floty (cf. ADR-0006 § 2 — pipeline
 * fixe en 8 étapes).
 *
 *   1. Récupération du contexte (caractéristiques fiscales courantes)
 *   2. Classifications (méthode CO₂, catégorie polluants)
 *   3. Cumul (déjà fourni par l'appelant via le PipelineContext)
 *   4. Exonérations (collecte des verdicts ; court-circuit hors scope)
 *   5. Abatements (vide en 2024)
 *   6. Tarification (CO₂ + polluants)
 *   7. Prorata + arrondi (Transversal)
 *   8. Output structuré (PipelineResult)
 *
 * Le pipeline lit les règles applicables via le {@see FiscalRuleRegistry}
 * pour l'année du contexte. Cela rend l'exécution agnostique de l'année
 * (2024, 2025, …) — c'est le registry qui sait quelles classes
 * appliquer pour quelle année.
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

        // Application des verdicts d'exonération sur les tarifs (avant
        // prorata) — préserve la sémantique du calculator legacy.
        $context = $this->applyExemptionsToTariffs($context);

        // Étape 7 — Transversales (prorata + arrondi)
        foreach ($this->filterByType($rules, TransversalRule::class) as $rule) {
            $context = $rule->apply($context);
        }

        // Application finale des verdicts pleins (tariffs déjà mis à 0
        // pour Both ou Co2Only/PollutantsOnly via applyExemptionsToTariffs).
        // Étape 8 — Sortie structurée
        return $this->buildResult($context);
    }

    private function validateInputs(PipelineContext $context): void
    {
        if (! $this->yearContext->isSupported($context->fiscalYear)) {
            throw FiscalCalculationException::yearNotSupported($context->fiscalYear);
        }
        if ($context->daysAssignedToCompany < 0) {
            throw FiscalCalculationException::negativeDays($context->daysAssignedToCompany);
        }
        if ($context->cumulativeDaysForPair < $context->daysAssignedToCompany) {
            throw FiscalCalculationException::cumulInferiorToAssigned(
                $context->cumulativeDaysForPair,
                $context->daysAssignedToCompany,
            );
        }
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
            if ($verdict->zeroesFullYearTariffs) {
                $hasZeroingTariffs = true;
            }
            if ($verdict->scope !== null) {
                $covers[] = $verdict->scope;
            }
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
        $reasons = array_values(array_filter(array_map(
            static fn (ExemptionVerdict $v): ?string => $v->reason,
            $verdicts,
        ), static fn (?string $r): bool => $r !== null));

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
            if ($verdict->scope === ExemptionScope::Both && ! $verdict->zeroesFullYearTariffs) {
                $lcdExempt = true;
            }
        }

        $totalDue = round(
            ($context->co2Due ?? 0.0) + ($context->pollutantsDue ?? 0.0),
            2,
            PHP_ROUND_HALF_UP,
        );

        return new PipelineResult(
            daysAssigned: $context->daysAssignedToCompany,
            cumulativeDaysForPair: $context->cumulativeDaysForPair,
            daysInYear: $context->daysInYear,
            lcdExempt: $lcdExempt,
            electricExempt: $electricExempt,
            handicapExempt: $handicapExempt,
            co2Method: $context->resolvedCo2Method ?? HomologationMethod::Pa,
            co2FullYearTariff: $context->co2FullYearTariff ?? 0.0,
            co2Due: $context->co2Due ?? 0.0,
            pollutantCategory: $context->resolvedPollutantCategory ?? PollutantCategory::MostPolluting,
            pollutantsFullYearTariff: $context->pollutantsFullYearTariff ?? 0.0,
            pollutantsDue: $context->pollutantsDue ?? 0.0,
            totalDue: $totalDue,
            exemptionReasons: $reasons,
            appliedRuleCodes: $context->appliedRuleCodes,
        );
    }
}
