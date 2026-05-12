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
 * taxe pleine) restent dans {@see VehicleUsageStatsData}, alimentés par
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
        /**
         * Montant loyer annuel (somme cross-entreprises des 12 facturations
         * mensuelles) en euros. `null` si au moins 1 mois lève
         * MissingPricing (= tarif annuel manquant pour le véhicule).
         * Phase 13 D5.10.L.
         */
        public ?float $rentalPrice = null,
    ) {}
}
