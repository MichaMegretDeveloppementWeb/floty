<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Year2024\Year2024Boot;
use App\Providers\FiscalServiceProvider;

/**
 * Contract for registering a fiscal year's rules into the
 * {@see FiscalRuleRegistry}.
 *
 * Each year declares its rules in its own `Year{YYYY}Boot` class.
 * {@see FiscalServiceProvider} discovers boot classes via
 * `config('floty.fiscal.year_boots')` so adding a new year does not
 * require provider changes.
 *
 * Conventions:
 * - One implementation per year (e.g. {@see Year2024Boot}).
 * - `year()` returns the civil year (e.g. 2024).
 * - `rules()` returns class-strings (FQCNs), not instances; the
 *   registry resolves them through the container.
 * - Order is not significant for the registry but the logical order
 *   (Classification → Exemption → Pricing → Transversal) is kept for
 *   readability and to match `taxes-rules/{year}.md`.
 *
 * Documentary-only rules (informative) are declared via
 * {@see informativeRules()}. They share the metadata shape of pipeline
 * rules but are never resolved by the registry: only the seeder
 * consumes them to populate the `fiscal_rules` index.
 */
interface FiscalYearBoot
{
    /**
     * Civil year these rules apply to.
     */
    public function year(): int;

    /**
     * Pipeline (calculating) rule classes registered for this year.
     * Resolved by the Laravel container via
     * {@see FiscalRuleRegistry::rulesForYear()}.
     *
     * @return list<class-string<FiscalRule>>
     */
    public function rules(): array;

    /**
     * Documentary-only rule classes. Not executed by the pipeline;
     * seeded into `fiscal_rules` to feed the "Règles de calcul" page.
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array;
}
