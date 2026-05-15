<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Aperçu opérationnel du Dashboard · lentille « Exploration » (état
 * immédiat). Chantier η Phase 4.
 *
 * Composé de · `last30DaysHeatmap` (grille véhicules × 30 jours) pour
 * repérer en un coup d'œil les véhicules sous-utilisés ou en surcharge
 * dans la période immédiate.
 */
#[TypeScript]
final class DashboardActivityData extends Data
{
    public function __construct(
        /** @var list<DashboardVehicleHeatmapData> */
        public array $last30DaysHeatmap,
    ) {}
}
