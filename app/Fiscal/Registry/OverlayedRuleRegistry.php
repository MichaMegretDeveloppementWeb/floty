<?php

declare(strict_types=1);

namespace App\Fiscal\Registry;

use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2025\Exemption\R2025_008_ReductiveUnavailability;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Registry overlayé qui substitue la règle LCD canonique (R-YYYY-021)
 * par un decorator porteur d'opt-outs pour le calcul d'une déclaration
 * spécifique (Phase 11 D5.1, généralisé Bloc 4 Phase J pour 2024/2025).
 *
 * **Pourquoi cette classe** · la règle canonique R-YYYY-021 ne connaît
 * pas les décisions humaines de revue. Pour qu'une décision
 * « Requalified » sur un cluster retire l'exonération LCD aux contrats
 * du cluster sans toucher à leur durée, on substitue la règle par un
 * decorator runtime (R2024_021_WithOptOuts ou R2025_021_WithOptOuts).
 * Cette substitution doit s'appliquer également aux **règles consommatrices**
 * qui dépendent de R-YYYY-021 par injection (R-YYYY-008 réducteurs).
 *
 * **Architecture multi-année** · le registry est scopé à une année
 * fiscale (`$fiscalYear`) parce qu'une déclaration porte sur un couple
 * `(company, year)` fixé. Le decorator passé doit être de l'année
 * correspondante (vérifié dans le constructor). Le mapping
 * canonique → decorator est résolu via {@see resolveRuleInstance()}
 * en switchant sur l'année.
 *
 * **Liste des consommateurs de LcdQualifier** · V1 ne connaît qu'un seul
 * consommateur dans le pipeline (R-YYYY-008 ReductiveUnavailability). Si
 * une future règle dépend aussi du `LcdQualifier` via injection, on
 * ajoute son `class-string` + sa logique d'instanciation manuelle dans
 * {@see resolveRuleInstance()}.
 *
 * **Usage** · instancier ad-hoc dans le `DeclarationFiscalEngine` (D5.2),
 * passer à un {@see App\Fiscal\Pipeline\RuleEffectiveSegmenter} frais (le
 * segmenteur global singleton n'est PAS réutilisé pour éviter pollution
 * du cache cross-déclaration).
 */
final class OverlayedRuleRegistry extends FiscalRuleRegistry
{
    /**
     * @param  ExemptionRule&LcdQualifier  $lcdDecorator  decorator runtime
     *                                                    de la règle LCD canonique de l'année concernée (porteur
     *                                                    des opt-outs) · doit être de l'année `$fiscalYear`.
     */
    public function __construct(
        private readonly Container $container,
        FiscalRuleRegistry $base,
        private readonly ExemptionRule&LcdQualifier $lcdDecorator,
        private readonly int $fiscalYear,
    ) {
        parent::__construct($container);

        // Garde-fou cohérence · le decorator doit appartenir à l'année.
        if ($lcdDecorator->applicabilityStart()->year !== $fiscalYear) {
            throw new InvalidArgumentException(sprintf(
                'OverlayedRuleRegistry · le décorateur LCD année %d ne correspond pas à $fiscalYear=%d.',
                $lcdDecorator->applicabilityStart()->year,
                $fiscalYear,
            ));
        }

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
        // Substitution scopée à l'année fiscale du registry (Phase J).
        // Les classes des autres années passent par la résolution
        // standard du container.
        if ($this->fiscalYear === 2024) {
            // Substitution directe · R-2024-021 → decorator.
            if ($class === R2024_021_ShortTermRental::class) {
                return $this->lcdDecorator;
            }

            // Substitution indirecte · R-2024-008 dépend du LcdQualifier
            // via constructor. On l'instancie manuellement en injectant
            // le decorator pour que la qualification LCD vue par
            // R-2024-008 reflète aussi les opt-outs.
            if ($class === R2024_008_ReductiveUnavailability::class) {
                return new R2024_008_ReductiveUnavailability($this->lcdDecorator);
            }
        }

        if ($this->fiscalYear === 2025) {
            // Substitution directe · R-2025-021 → decorator.
            if ($class === R2025_021_ShortTermRental::class) {
                return $this->lcdDecorator;
            }

            // Substitution indirecte · R-2025-008 dépend du LcdQualifier
            // via constructor.
            if ($class === R2025_008_ReductiveUnavailability::class) {
                return new R2025_008_ReductiveUnavailability($this->lcdDecorator);
            }
        }

        // Toute autre règle · résolution standard via le container.
        return $this->container->make($class);
    }
}
