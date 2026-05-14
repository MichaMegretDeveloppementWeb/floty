<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Models\Contract;
use Carbon\CarbonImmutable;

/**
 * Decorator de {@see R2024_021_ShortTermRental} (Phase 11 D5.1).
 *
 * **Pourquoi ce decorator** : les décisions humaines de revue
 * (« Requalified » sur un cluster LCD) doivent retirer l'exonération
 * LCD à un sous-ensemble de contrats sans toucher à leur durée et sans
 * modifier la règle canonique R-2024-021. Le mécanisme est porté par
 * la **recette** (le `DeclarationFiscalEngine` qui orchestre le calcul
 * d'une déclaration), pas par la règle (qui reste pure et conforme à
 * la lecture stricte BOFiP § 180-190).
 *
 * **Sémantique** :
 * - `isShortTermRental(Contract)` retourne `false` si le contrat est
 *   dans la liste opt-out, sinon délègue à la règle wrappée.
 * - `evaluate(PipelineContext)` ré-implémente la collecte des verdicts
 *   en utilisant `$this->isShortTermRental()` (la version filtrée),
 *   pour que les contrats opt-out ne soient PAS comptés en exempt
 *   days par R-2024-002.
 * - Toutes les autres méthodes (métadonnées, applicability, ruleCode,
 *   taxesConcerned) délèguent à la règle wrappée. **Le ruleCode reste
 *   `R-2024-021`** : c'est toujours la même règle légale, juste avec
 *   une opt-out runtime.
 *
 * **Architecture** : utilisé exclusivement par
 * {@see App\Fiscal\Registry\OverlayedRuleRegistry} pour substituer
 * R-2024-021 dans le calcul d'une déclaration spécifique.
 */
final readonly class R2024_021_WithOptOuts implements ExemptionRule, LcdQualifier
{
    /**
     * @param  list<int>  $optOutContractIds  IDs des contrats requalifiés (= NON-LCD)
     */
    public function __construct(
        private R2024_021_ShortTermRental $wrapped,
        private array $optOutContractIds,
    ) {}

    public function isShortTermRental(Contract $contract): bool
    {
        if (in_array($contract->id, $this->optOutContractIds, true)) {
            return false;
        }

        return $this->wrapped->isShortTermRental($contract);
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        $exemptDays = 0;
        $lcdContractsCount = 0;

        foreach ($context->contractsForPair as $contract) {
            // Utilise la version filtrée (`$this`), pas la version wrappée :
            // c'est tout l'intérêt du decorator.
            if (! $this->isShortTermRental($contract)) {
                continue;
            }
            $exemptDays += $contract->countDaysInYear($context->fiscalYear);
            $lcdContractsCount++;
        }

        if ($exemptDays === 0) {
            return ExemptionVerdict::notExempt();
        }

        return ExemptionVerdict::partialDays(
            $exemptDays,
            sprintf(
                'Exonération LCD - %d location%s courte%s (%d jour%s) (CIBS L. 421-129 / L. 421-141, BOFiP § 180-190)',
                $lcdContractsCount,
                $lcdContractsCount > 1 ? 's' : '',
                $lcdContractsCount > 1 ? 's' : '',
                $exemptDays,
                $exemptDays > 1 ? 's' : '',
            ),
            $this->ruleCode(),
        );
    }

    public function ruleCode(): string
    {
        return $this->wrapped->ruleCode();
    }

    public function name(): string
    {
        return $this->wrapped->name();
    }

    public function description(): string
    {
        return $this->wrapped->description();
    }

    public function ruleType(): RuleType
    {
        return $this->wrapped->ruleType();
    }

    public function displayOrder(): int
    {
        return $this->wrapped->displayOrder();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return $this->wrapped->legalBasis();
    }

    public function isActive(): bool
    {
        return $this->wrapped->isActive();
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return $this->wrapped->taxesConcerned();
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return $this->wrapped->applicabilityStart();
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return $this->wrapped->applicabilityEnd();
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return $this->wrapped->pedagogicalContent();
    }
}
