<?php

declare(strict_types=1);

namespace App\Fiscal\Registry;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Contracts\FiscalRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;

/**
 * Catalog of fiscal rule classes per year (ADR-0006 § 3, ADR-0022).
 *
 * The `year → list<class-string<FiscalRule>>` mapping is set at boot
 * via {@see register()} (typically in `FiscalServiceProvider`). The
 * pipeline and the display layer both query {@see rulesForYear()},
 * which resolves the classes through the Laravel container. Rules are
 * stateless singletons.
 *
 * Per ADR-0022, this registry is the single read entry point for
 * fiscal rules in production. The `fiscal_rules` DB table is a thin
 * mirror index (id + rule_code + fiscal_year + code_reference)
 * maintained by the seeder.
 */
class FiscalRuleRegistry
{
    /**
     * @var array<int, list<class-string<FiscalRule>>>
     */
    private array $byYear = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  list<class-string<FiscalRule>>  $ruleClasses
     */
    public function register(int $year, array $ruleClasses): void
    {
        $this->byYear[$year] = $ruleClasses;
    }

    /**
     * @return list<FiscalRule>
     */
    public function rulesForYear(int $year): array
    {
        if (! isset($this->byYear[$year])) {
            throw FiscalCalculationException::yearNotSupported($year);
        }

        return array_map(
            fn (string $class): FiscalRule => $this->container->make($class),
            $this->byYear[$year],
        );
    }

    /**
     * Years for which the registry holds at least one rule.
     *
     * @return list<int>
     */
    public function registeredYears(): array
    {
        return array_keys($this->byYear);
    }

    /**
     * Rule class-strings registered for a year, without resolving them
     * into instances. Used by {@see OverlayedRuleRegistry} to substitute
     * some instances while keeping the reference list.
     *
     * @return list<class-string<FiscalRule>>
     */
    public function classesForYear(int $year): array
    {
        if (! isset($this->byYear[$year])) {
            throw FiscalCalculationException::yearNotSupported($year);
        }

        return $this->byYear[$year];
    }

    /**
     * Filters the rules of a year by their applicability window.
     *
     * Returns rules whose `[applicabilityStart, applicabilityEnd]`
     * window contains `$date`. Comparison at day granularity. A rule
     * with `applicabilityEnd === null` is "valid indefinitely".
     *
     * Propagates `yearNotSupported` if the year is not registered.
     *
     * `$date` is not constrained to belong to `$year`: the caller may
     * query an out-of-year date, in which case the list is typically
     * empty.
     *
     * @return list<FiscalRule>
     */
    public function rulesEffectiveAt(int $year, CarbonImmutable $date): array
    {
        $day = $date->startOfDay();

        return array_values(array_filter(
            $this->rulesForYear($year),
            static function (FiscalRule $rule) use ($day): bool {
                $start = $rule->applicabilityStart()->startOfDay();
                if ($day->lessThan($start)) {
                    return false;
                }
                $end = $rule->applicabilityEnd();
                if ($end === null) {
                    return true;
                }

                return ! $day->greaterThan($end->startOfDay());
            },
        ));
    }
}
