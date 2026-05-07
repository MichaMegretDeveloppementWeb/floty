<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Statistiques annuelles consolidées flotte · une ligne par exercice.
 * Alimente la lentille « Évolution » (graphique barres multi-années)
 * du Dashboard (chantier η Phase 4, enrichi Phase 14.W avec recettes
 * locatives).
 *
 * Les dimensions sont alignées sur `DashboardKpiData` (les 3 lentilles
 * Présent/Évolution/Exploration partagent les mêmes pivots).
 *
 * **Sémantique temporelle asymétrique** :
 * - 4 dimensions YTD : `joursVehicule`, `contracts`, `taxesDues`,
 *   `tauxOccupation`. Années passées = full year, année courante = du
 *   1er janvier au jour courant (partielle, signalée « 2026 (en cours) »).
 * - 1 dimension full year : `recettesLocativesCents`. Pour l'année en
 *   cours, la valeur reflète le CA prévu sur l'année entière (mois
 *   réalisés + mois futurs), cohérent avec la KPI card. La barre
 *   « (en cours) » garde son opacité 60 % pour signaler la nature
 *   prévisionnelle.
 */
#[TypeScript]
final class DashboardYearHistoryData extends Data
{
    public function __construct(
        public int $year,
        /** Vrai si l'année est l'année calendaire courante (donc partielle). */
        public bool $isCurrentYear,
        public int $joursVehicule,
        /** Total contrats ayant une activité sur l'année. */
        public int $contracts,
        public float $taxesDues,
        public float $tauxOccupation,
        /**
         * Recettes locatives HT plein année (jan-déc) en centimes.
         * Pour l'année courante, inclut les contrats futurs (CA prévu).
         */
        public int $recettesLocativesCents,
    ) {}
}
