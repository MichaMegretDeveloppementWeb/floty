<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Data\User\Fiscal\Pedagogical\RulePedagogicalContentData;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\FiscalRule as FiscalRuleContract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A fiscal rule as presented on the "Règles de calcul" page and inside
 * vehicle/contract breakdowns (ADR-0022).
 *
 * Built exclusively from the PHP rule class via {@see self::fromRule()}.
 * The `fiscal_rules` table only provides the stable `$id` for potential
 * FK references.
 *
 * `applicabilityStart` / `applicabilityEnd` are clipped to the consulted
 * year; `isFullYear` lets the UI quietly render the standard case and
 * highlight partial-year rules.
 */
#[TypeScript]
final class FiscalRuleListItemData extends Data
{
    /**
     * @param  list<TaxType>  $taxesConcerned
     * @param  list<array{type: string, article?: string, reference?: string, paragraph?: string, url?: string, consulted_at?: string}>  $legalBasis
     */
    public function __construct(
        public int $id,
        public string $ruleCode,
        public string $name,
        public string $description,
        public RuleType $ruleType,
        public array $taxesConcerned,
        public array $legalBasis,
        public ?RulePedagogicalContentData $pedagogicalContent,
        public bool $isActive,
        public string $applicabilityStartInYear,
        public string $applicabilityEndInYear,
        public bool $isFullYear,
    ) {}

    /**
     * Build from the PHP rule class, the single source of truth. `$id`
     * comes from the DB index `fiscal_rules`, fetched in batch via
     * `FiscalRuleReadRepository::findIdsByCodeForYear()`.
     */
    public static function fromRule(FiscalRuleContract $rule, int $year, int $id): self
    {
        $yearStart = sprintf('%d-01-01', $year);
        $yearEnd = sprintf('%d-12-31', $year);

        $ruleStart = $rule->applicabilityStart()->toDateString();
        $ruleEnd = $rule->applicabilityEnd()?->toDateString() ?? $yearEnd;

        // ISO `YYYY-MM-DD` strings: lexicographic order == chronological order.
        $startInYear = $ruleStart > $yearStart ? $ruleStart : $yearStart;
        $endInYear = $ruleEnd < $yearEnd ? $ruleEnd : $yearEnd;

        return new self(
            id: $id,
            ruleCode: $rule->ruleCode(),
            name: $rule->name(),
            description: $rule->description(),
            ruleType: $rule->ruleType(),
            taxesConcerned: $rule->taxesConcerned(),
            legalBasis: $rule->legalBasis(),
            pedagogicalContent: RulePedagogicalContentData::fromVo($rule->pedagogicalContent()),
            isActive: $rule->isActive(),
            applicabilityStartInYear: $startInYear,
            applicabilityEndInYear: $endInYear,
            isFullYear: $startInYear === $yearStart && $endInYear === $yearEnd,
        );
    }
}
