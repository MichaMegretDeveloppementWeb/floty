<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Carte « Recettes locatives » du Dashboard · isolée des 4 KPIs fiscaux
 * (chantier perf Dashboard 2026-05-17) pour être chargée en
 * `Inertia::defer` indépendant · le calcul `BillingBreakdownService`
 * itère sur toutes les entreprises et ajoute ~60 queries SQL au chemin
 * critique quand il est groupé avec les KPIs fiscaux.
 *
 * **Sémantique full year** · CA HT cumulé sur l'année calendaire entière
 * (jan-déc), réalisé + prévu. Pour une société de location, le CA annuel
 * attendu est plus parlant que le YTD (qui serait toujours sous-estimé
 * en début d'année). Cohérent avec la sémantique pré-refacto. Caption
 * frontend lève l'ambiguïté.
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
        /** Recettes Y-1 plein année (jan-déc), ou `null` si Y-1 = 0. */
        public ?int $previousYearRecettesLocativesCents,
        /** Variation relative % vs Y-1, ou `null` si Y-1 = 0. */
        public ?float $deltaRecettesLocativesPercent,
        /** Année comparée (Y-1), null si pas de comparaison. */
        public ?int $previousYear,
    ) {}
}
