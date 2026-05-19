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
