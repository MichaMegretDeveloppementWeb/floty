<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Aperçu opérationnel du Dashboard — lentille « Exploration » (état
 * immédiat). Chantier η Phase 4.
 *
 * Compose deux blocs visuels :
 *   - `last30DaysHeatmap` : grille véhicules × 30 jours pour repérer
 *     en un coup d'œil les véhicules sous-utilisés ou en surcharge
 *     dans la période immédiate.
 *   - `topExpensiveVehicles` : top 3 véhicules par taxe YTD pour
 *     repérer les véhicules les plus coûteux fiscalement.
 */
#[TypeScript]
final class DashboardActivityData extends Data
{
    public function __construct(
        /** @var list<DashboardVehicleHeatmapData> */
        public array $last30DaysHeatmap,
        /** @var list<DashboardTopVehicleData> */
        public array $topExpensiveVehicles,
    ) {}
}
