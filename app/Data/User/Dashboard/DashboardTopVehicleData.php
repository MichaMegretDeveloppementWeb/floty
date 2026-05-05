<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne du « Top véhicules par taxe YTD » — Dashboard, lentille
 * Exploration (chantier η Phase 4).
 *
 * Le calcul de `taxYearToDate` agrège CO₂ + polluants pour le véhicule
 * concerné, sur les contrats actifs du 1er janvier à la date courante.
 * Permet à Renaud d'identifier rapidement les véhicules les plus
 * coûteux fiscalement et d'arbitrer (les retirer si trop chers).
 */
#[TypeScript]
final class DashboardTopVehicleData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public string $brand,
        public string $model,
        public float $taxYearToDate,
    ) {}
}
