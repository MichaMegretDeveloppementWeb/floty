<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Dashboard "Recettes locatives" KPI card, served independently of the
 * fiscal KPIs.
 *
 * Sums the full calendar year (realized + planned). For a rental company
 * the expected annual revenue is more meaningful than a YTD value, which
 * would always look underestimated early in the year.
 */
#[TypeScript]
final class DashboardKpiRecettesData extends Data
{
    public function __construct(
        /** Current calendar year (fixed; not the selector value). */
        public int $year,
        /**
         * Full-year net rental revenue (cents, ex-VAT) for the current
         * year across all companies. Reflects the contractual revenue,
         * not what has actually been invoiced.
         */
        public int $recettesLocativesCents,
    ) {}
}
