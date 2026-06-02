<?php

declare(strict_types=1);

namespace App\Data\Shared;

use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Services\Fiscal\AvailableYearsResolver;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Selectable years scope exposed as a per-page Inertia prop (not shared)
 * so each section keeps its own year default with no implicit coupling
 * across pages.
 *
 * Fields:
 *   - `currentYear`: real calendar year used as default in pickers.
 *   - `minYear`: lowest available year inferred from active contracts.
 *   - `availableYears`: contiguous `[minYear, ..., max]` range.
 *
 * Build through {@see fromResolver()} in production. The constructor
 * stays public for tests and custom-value injection.
 */
#[TypeScript]
final class YearScopeData extends Data
{
    /**
     * @param  list<int>  $availableYears
     */
    public function __construct(
        public int $currentYear,
        public int $minYear,
        public array $availableYears,
    ) {}

    /**
     * Build from the singleton fiscal resolver. Each of the three resolver
     * calls is memoized for the request via the singleton.
     */
    public static function fromResolver(AvailableYearsResolver $resolver): self
    {
        return new self(
            currentYear: $resolver->currentYear(),
            minYear: $resolver->minYear(),
            availableYears: $resolver->availableYears(),
        );
    }

    /**
     * Build the union scope used by the Planning pages and the Dashboard
     * evolution chart: the years for which fiscal rules are coded
     * (registry) act as the guaranteed minimum, extended by any year that
     * already holds contracts and by the current calendar year. This lets
     * the user reach a year to enter historical contracts (e.g. 2024 on a
     * fresh instance, before any contract exists) or to anticipate a future
     * year, even before its fiscal rules are coded.
     *
     * `currentYear` stays the real calendar year (default selection), even
     * when it has no coded fiscal rules.
     *
     * Canonical source of the union range: the Dashboard reuses
     * `->availableYears` here so its chart range matches the Planning
     * selector exactly.
     */
    public static function fromResolverAndRegistry(
        AvailableYearsResolver $resolver,
        FiscalRuleRegistry $registry,
    ): self {
        $current = $resolver->currentYear();

        // `minYear()`/`maxYear()` already fold in the current year and the
        // contract bounds; union them with the registered fiscal years.
        $candidates = [
            $current,
            $resolver->minYear(),
            $resolver->maxYear(),
            ...$registry->registeredYears(),
        ];

        return new self(
            currentYear: $current,
            minYear: min($candidates),
            availableYears: range(min($candidates), max($candidates)),
        );
    }

    /**
     * Build from the fiscal engine scope (years for which rule sets are
     * registered). Used by the "Règles de calcul" page since it consults
     * versioned scales rather than business-data periods.
     *
     * `currentYear` defaults to the latest registered year; if nothing is
     * registered, falls back to the current calendar year.
     */
    public static function fromRegistry(FiscalRuleRegistry $registry): self
    {
        $years = $registry->registeredYears();
        sort($years);

        if ($years === []) {
            $current = (int) CarbonImmutable::now()->year;

            return new self(currentYear: $current, minYear: $current, availableYears: [$current]);
        }

        return new self(
            currentYear: (int) max($years),
            minYear: (int) min($years),
            availableYears: $years,
        );
    }
}
