<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * KPIs affichés sur le Dashboard utilisateur.
 */
#[TypeScript]
final class DashboardStatsData extends Data
{
    public function __construct(
        public int $vehiclesCount,
        public int $companiesCount,
        public int $assignmentsYear,
        public int $fiscalRulesCount,
        public float $totalTaxDue,
    ) {}
}
