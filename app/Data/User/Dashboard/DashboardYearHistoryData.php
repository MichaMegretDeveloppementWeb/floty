<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Statistiques annuelles consolidées flotte — une ligne par exercice.
 * Alimente la lentille « Évolution » (graphique barres multi-années)
 * du Dashboard (chantier η Phase 4).
 *
 * Les 4 dimensions sont les mêmes que `DashboardKpiData` (les 3
 * lentilles Présent/Évolution/Exploration partagent les mêmes pivots).
 *
 * Les années passées sont calculées **complètes** (1er janvier au 31
 * décembre). L'année en cours est partielle (1er janvier à la date
 * courante) — l'utilisateur doit le savoir et l'UI l'indique
 * explicitement (label « 2026 (en cours) » par exemple).
 */
#[TypeScript]
final class DashboardYearHistoryData extends Data
{
    public function __construct(
        public int $year,
        /** Vrai si l'année est l'année calendaire courante (donc partielle). */
        public bool $isCurrentYear,
        public int $joursVehicule,
        public int $contractsActifs,
        public float $taxesDues,
        public float $tauxOccupation,
    ) {}
}
