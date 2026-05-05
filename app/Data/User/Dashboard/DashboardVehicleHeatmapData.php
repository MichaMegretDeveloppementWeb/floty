<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de la heatmap « 30 derniers jours » : un véhicule + son statut
 * jour par jour. Lentille « Exploration » du Dashboard (chantier η
 * Phase 4).
 *
 * Le tableau `days` contient exactement 30 entrées triées du plus
 * ancien au plus récent (J-29 → J), une par jour. Le `status` agrège
 * contrats + indispos :
 *   - `'occupied'` : ≥ 1 contrat actif sur le véhicule ce jour-là
 *   - `'unavailable'` : indispo enregistrée (toutes catégories)
 *   - `'free'` : ni contrat ni indispo (libre pour location)
 *
 * Si un véhicule a été retiré de la flotte avant J-29, il n'apparaît
 * pas dans la heatmap.
 */
#[TypeScript]
final class DashboardVehicleHeatmapData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public string $brand,
        public string $model,
        /** @var list<DashboardHeatmapDayData> */
        public array $days,
    ) {}
}
