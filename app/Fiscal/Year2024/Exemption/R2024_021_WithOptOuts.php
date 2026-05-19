<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Registry\OverlayedRuleRegistry;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Models\Contract;
use Carbon\CarbonImmutable;

/**
 * Decorator for {@see R2024_021_ShortTermRental} that carries an
 * opt-out contract list.
 *
 * Human "Requalified" review decisions on an LCD cluster must strip
 * the LCD exemption from a subset of contracts without altering their
 * duration and without modifying the canonical R-2024-021 rule. The
 * mechanism is carried by the recipe (the `DeclarationFiscalEngine`
 * orchestrating a declaration's calculation), not the rule (which
 * stays pure and faithful to BOFiP § 180-190).
 *
 * Semantics:
 * - `isShortTermRental(Contract)` returns `false` if the contract is
 *   in the opt-out list, otherwise delegates to the wrapped rule.
 * - `evaluate(PipelineContext)` re-implements verdict collection
 *   using `$this->isShortTermRental()` (the filtered version), so
 *   opt-out contracts are NOT counted as exempt days by R-2024-002.
 * - All other methods (metadata, applicability, ruleCode,
 *   taxesConcerned) delegate to the wrapped rule. The ruleCode stays
 *   `R-2024-021`: same legal rule, only with a runtime opt-out.
 *
 * Used exclusively by {@see OverlayedRuleRegistry}
 * to swap R-2024-021 for a specific declaration calculation.
 */
final readonly class R2024_021_WithOptOuts implements ExemptionRule, LcdQualifier
{
    /**
     * @param  list<int>  $optOutContractIds  IDs of requalified contracts (= NON-LCD)
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
            // Use the filtered version (`$this`), not the wrapped one ·
            // the whole point of the decorator.
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
