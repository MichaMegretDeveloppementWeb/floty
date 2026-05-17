<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Carte « Recettes locatives » du Dashboard · isolée des 4 KPIs fiscaux
 * (chantier perf Dashboard 2026-05-17).
 *
 * **Sémantique full year** · CA HT cumulé sur l'année calendaire entière
 * (jan-déc), réalisé + prévu. Pour une société de location, le CA annuel
 * attendu est plus parlant que le YTD (qui serait toujours sous-estimé
 * en début d'année).
 *
 * **Comparaison Y-1 supprimée** (v3) · l'historique multi-années
 * remplace les trends Y-1 par carte. Gain CPU · le batch recettes
 * n'est plus exécuté 2× (year + year-1) au mount Dashboard.
 */
#[TypeScript]
final class DashboardKpiRecettesData extends Data
{
    public function __construct(
        /** Année calendaire courante (figée, ≠ sélecteur). */
        public int $year,
        /**
         * Recettes locatives HT (cents) plein année courante (toutes
         * entreprises × tous mois 1..12). Calcul via
         * `BillingBreakdownService` en mode partiel (mois sans tarif
         * annuel exclus). Indépendant de l'émission des factures · reflète
         * le CA contractuel, pas le facturé.
         */
        public int $recettesLocativesCents,
    ) {}
}
