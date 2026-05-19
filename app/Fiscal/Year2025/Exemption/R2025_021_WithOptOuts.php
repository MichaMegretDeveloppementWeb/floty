<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Exemption;

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
 * Decorator of {@see R2025_021_ShortTermRental}, strict reproduction
 * of R-2024-021_WithOptOuts.
 *
 * Human review decisions ("Requalified" on an LCD cluster) must remove
 * the LCD exemption from a subset of contracts without touching their
 * duration and without modifying the canonical rule R-2025-021.
 *
 * Semantics identical to R-2024-021_WithOptOuts: `isShortTermRental`
 * returns `false` if the contract is in the opt-out list, otherwise
 * delegates to the wrapped rule. `evaluate()` uses the filtered
 * version. The `ruleCode` remains `R-2025-021` (same legal rule,
 * runtime opt-out).
 *
 * Used exclusively by OverlayedRuleRegistry to substitute R-2025-021
 * in the calculation of a specific declaration. NOT listed in
 * `Year2025Boot::rules()`.
 */
final readonly class R2025_021_WithOptOuts implements ExemptionRule, LcdQualifier
{
    /**
     * @param  list<int>  $optOutContractIds  IDs of requalified contracts (= NON-LCD)
     */
    public function __construct(
        private R2025_021_ShortTermRental $wrapped,
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
