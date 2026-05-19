<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One annual data point of the Dashboard "Évolution" chart, scoped to a
 * single dimension. Each of the 4 dimensions (jours-véhicule, contracts,
 * taxes, recettes) is served as its own Inertia prop and may be hydrated
 * lazily on tab click.
 */
#[TypeScript]
final class DashboardHistoryPointData extends Data
{
    public function __construct(
        public int $year,
        /** True when this year is the current calendar year (partial). */
        public bool $isCurrentYear,
        /**
         * Int for jours-véhicule / contracts / recettes (cents); float for
         * taxes due (€). Mapped as `number` on the TypeScript side.
         */
        public int|float $value,
    ) {}
}
