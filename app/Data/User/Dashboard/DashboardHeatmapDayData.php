<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cellule de la heatmap « 30 derniers jours » — un jour pour un
 * véhicule donné (chantier η Phase 4).
 *
 * `status` ∈ { 'occupied', 'unavailable', 'free' }.
 */
#[TypeScript]
final class DashboardHeatmapDayData extends Data
{
    public function __construct(
        /** Date ISO 8601 (Y-m-d). */
        public string $date,
        public string $status,
    ) {}
}
