<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stats annuelles d'un véhicule pour une année donnée — alimente à la
 * fois les KPIs « Présent » (carte du haut, année calendaire courante)
 * et le mini-tableau « Évolution » (section Historique, années passées).
 *
 * Doctrine temporelle (chantier η Phase 2) :
 *   - Présent  : `kpiYear` + `kpiStats` figés sur l'année courante.
 *   - Évolution : `history[]` couvre `[minYear..currentYear-1]`, lignes
 *     neutres (zéros) pour les années sans contrat sur le véhicule.
 *
 * Les calculs détaillés (timeline 52 semaines, breakdown par entreprise,
 * coût plein) restent dans {@see VehicleUsageStatsData}, alimentés par
 * la lentille « Exploration » (sélecteur d'année partagé).
 */
#[TypeScript]
final class VehicleYearStatsData extends Data
{
    public function __construct(
        public int $year,
        public int $daysUsed,
        public int $contractsCount,
        public float $actualTax,
        public float $fullYearTax,
    ) {}
}
