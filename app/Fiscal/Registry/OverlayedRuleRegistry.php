<?php

declare(strict_types=1);

namespace App\Fiscal\Registry;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Exemption\R2024_021_WithOptOuts;
use Illuminate\Contracts\Container\Container;

/**
 * Registry overlayé qui substitue R-2024-021 par un decorator porteur
 * d'opt-outs pour le calcul d'une déclaration spécifique (Phase 11
 * D5.1).
 *
 * **Pourquoi cette classe** : la règle canonique R-2024-021 ne connaît
 * pas les décisions humaines de revue. Pour qu'une décision
 * « Requalified » sur un cluster retire l'exonération LCD aux contrats
 * du cluster sans toucher à leur durée, on substitue la règle par un
 * {@see R2024_021_WithOptOuts} runtime. Cette substitution doit
 * s'appliquer également aux **règles consommatrices** qui dépendent
 * de R-2024-021 par injection (R-2024-008 réducteurs).
 *
 * **Architecture** : étend {@see FiscalRuleRegistry} pour rester
 * substituable partout où la base est attendue (ex.
 * `RuleEffectiveSegmenter`). Override uniquement `rulesForYear($year)`
 * pour appliquer le mapping au moment de la résolution. Les autres
 * méthodes (`registeredYears`, `rulesEffectiveAt`, `classesForYear`,
 * `register`) héritent du parent et continuent de fonctionner
 * identiquement (le mapping year → classes est copié depuis la base
 * dans le constructor).
 *
 * **Liste hard-codée des consommateurs de R-2024-021** : V1 ne connaît
 * qu'un seul consommateur dans le pipeline (R-2024-008). Si une future
 * règle dépend aussi du `LcdQualifier` via injection, on ajoute son
 * `class-string` + sa logique d'instanciation manuelle dans
 * {@see resolveRuleInstance()}.
 *
 * **Usage** : instancier ad-hoc dans le `DeclarationFiscalEngine`
 * (D5.2), passer à un {@see App\Fiscal\Pipeline\RuleEffectiveSegmenter}
 * frais (le segmenteur global singleton n'est PAS réutilisé pour éviter
 * pollution du cache cross-déclaration).
 */
final class OverlayedRuleRegistry extends FiscalRuleRegistry
{
    public function __construct(
        private readonly Container $container,
        FiscalRuleRegistry $base,
        private readonly R2024_021_WithOptOuts $lcdDecorator,
    ) {
        parent::__construct($container);

        // Re-publie le mapping year → classes du registry de base pour
        // que `registeredYears()`, `classesForYear()` et `rulesEffectiveAt()`
        // soient cohérents sans avoir à les overrider. Les substitutions
        // d'instances sont faites à la lecture dans `rulesForYear()`.
        foreach ($base->registeredYears() as $year) {
            $this->register($year, $base->classesForYear($year));
        }
    }

    /**
     * @return list<FiscalRule>
     */
    public function rulesForYear(int $year): array
    {
        $rules = [];
        foreach ($this->classesForYear($year) as $class) {
            $rules[] = $this->resolveRuleInstance($class);
        }

        return $rules;
    }

    /**
     * @param  class-string<FiscalRule>  $class
     */
    private function resolveRuleInstance(string $class): FiscalRule
    {
        // Substitution directe : R-2024-021 → decorator.
        if ($class === R2024_021_ShortTermRental::class) {
            return $this->lcdDecorator;
        }

        // Substitution indirecte : R-2024-008 dépend du LcdQualifier
        // via constructor. On l'instancie manuellement en injectant le
        // decorator pour que la qualification LCD vue par R-2024-008
        // reflète aussi les opt-outs.
        if ($class === R2024_008_ReductiveUnavailability::class) {
            return new R2024_008_ReductiveUnavailability($this->lcdDecorator);
        }

        // Toute autre règle : résolution standard via le container.
        return $this->container->make($class);
    }
}
