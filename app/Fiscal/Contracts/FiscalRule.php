<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;
use Database\Seeders\FiscalRulesSeeder;

/**
 * Base contract for any Floty fiscal rule (ADR-0006 § 1).
 *
 * Concrete rules implement one of the five sub-contracts:
 *   - {@see ClassificationRule} (vehicle characteristic qualification)
 *   - {@see PricingRule}        (full-year tariff)
 *   - {@see ExemptionRule}      (conditional short-circuit)
 *   - {@see AbatementRule}      (input alteration before pricing)
 *   - {@see TransversalRule}    (prorata, rounding, unavailabilities…)
 *
 * `ruleCode()` is the immutable identifier published in
 * `taxes-rules/{year}.md` (format `R-{year}-{nnn}`, ADR-0009). It is
 * referenced in PDF snapshots and the "Règles de calcul" page.
 *
 * Each rule declares its applicability window via `applicabilityStart()`
 * and `applicabilityEnd()`. Most rules cover the whole fiscal year and
 * adopt {@see Concerns\AnnualRuleTrait}; partial rules implement those
 * methods directly.
 *
 * Per ADR-0022, the PHP classes are the single source of truth for all
 * rule metadata (name, description, tax type, display order, legal basis,
 * active flag). The `fiscal_rules` table is a thin mirror index
 * synchronised by {@see FiscalRulesSeeder} via these
 * accessors.
 */
interface FiscalRule
{
    public function ruleCode(): string;

    /**
     * Taxes the rule concerns. Used by the pipeline to filter rules by
     * the currently evaluated tax.
     *
     * @return list<TaxType>
     */
    public function taxesConcerned(): array;

    /**
     * Inclusive lower bound of the rule's applicability window. For an
     * annual rule this is `{year}-01-01 00:00:00`.
     */
    public function applicabilityStart(): CarbonImmutable;

    /**
     * Inclusive upper bound of the rule's applicability window. `null`
     * means open-ended (valid from `applicabilityStart()` onwards).
     */
    public function applicabilityEnd(): ?CarbonImmutable;

    /**
     * Short display name shown in the "Règles de calcul" page and PDF
     * snapshots.
     */
    public function name(): string;

    /**
     * Long description (plain text, multi-paragraph allowed) shown in
     * the accordion of the Règles page.
     */
    public function description(): string;

    /**
     * Functional sub-type that decides the rule's role in the pipeline
     * (ADR-0006 § 1).
     */
    public function ruleType(): RuleType;

    /**
     * Stable display order in the Règles page.
     */
    public function displayOrder(): int;

    /**
     * Structured legal basis. Each entry is an associative array whose
     * keys vary by `type` (CIBS / CGI / BOFIP / NOTICE). Shape matches
     * the `fiscal_rules.legal_basis` JSON column and is consumed by the
     * Spatie Data → TS Transformer for typed frontend exposure.
     *
     * @return list<array{type: string, article?: string, reference?: string, paragraph?: string, url?: string, consulted_at?: string}>
     */
    public function legalBasis(): array;

    /**
     * Whether the rule is active and published. Inactive rules remain
     * in the database to keep historical PDF snapshots referenceable.
     * Default `true` provided by {@see Concerns\RuleActiveByDefaultTrait};
     * disabled rules override directly with `isActive(): bool { return false; }`.
     */
    public function isActive(): bool;

    /**
     * Rich pedagogical content (title, pitch, body, example, formatted
     * brackets) displayed on the "Règles de calcul" page.
     */
    public function pedagogicalContent(): RulePedagogicalContent;
}
