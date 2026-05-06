<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Combinaison « jour / semaine / mois » optimale (= la moins chère pour
 * le client) couvrant un nombre de jours d'utilisation donné, à partir
 * des trois tarifs persistés sur le véhicule pour l'année concernée.
 *
 * **Modèle de couverture** (convention industrie loc) :
 *   - 1 mois unitaire → couvre 30 jours de location
 *   - 1 semaine unitaire → couvre 7 jours
 *   - 1 jour unitaire → couvre 1 jour
 *
 * Le mois civil **réel** dépend de février, des mois 30j et des mois
 * 31j ; ici on raisonne en jours d'usage **agrégés**, le découpage
 * civil étant déjà appliqué en amont par {@see BillingCalculator}.
 *
 * **Algorithme** : on énumère toutes les combinaisons (m, w, d) telles
 * que `m·30 + w·7 + d ≥ N` avec `m ∈ {0,1}`, `w ∈ {0..5}`, `d` ajusté
 * pour couvrir exactement le reste, puis on retient la combinaison de
 * coût minimal. L'espace de recherche est borné (≤ 12 combinaisons
 * pertinentes), le calcul est O(1) déterministe.
 *
 * **Hypothèse explicite** (cf. `roadmap_v12_facturation`, ligne « tarif
 * optimal pour le client (le moins cher) ») : aucun arbitrage en faveur
 * de la société émettrice. Si l'utilisateur souhaite renverser cette
 * politique (ex. minimum facturé d'une semaine), il faudra introduire
 * une stratégie alternative ; pour V1.2 c'est uniquement le moins
 * cher pour le client.
 */
final readonly class OptimalRateBreakdown
{
    private const int DAYS_PER_MONTH_UNIT = 30;

    private const int DAYS_PER_WEEK_UNIT = 7;

    /**
     * @param  int  $months  Nombre de « mois unitaires » facturés.
     * @param  int  $weeks  Nombre de « semaines unitaires » facturées.
     * @param  int  $days  Nombre de jours résiduels facturés à l'unité.
     * @param  int  $totalCents  Coût total en cents :
     *                           `months × monthly + weeks × weekly + days × daily`.
     */
    public function __construct(
        public int $months,
        public int $weeks,
        public int $days,
        public int $totalCents,
    ) {}

    /**
     * Calcule la combinaison optimale pour un nombre de jours d'usage
     * et trois tarifs (tous en cents, ≥ 0).
     *
     * Si `daysUsed === 0`, retourne un breakdown nul (rien à facturer).
     */
    public static function compute(
        int $daysUsed,
        int $dailyCents,
        int $weeklyCents,
        int $monthlyCents,
    ): self {
        if ($daysUsed === 0) {
            return new self(0, 0, 0, 0);
        }

        $bestCost = PHP_INT_MAX;
        $bestMonths = 0;
        $bestWeeks = 0;
        $bestDays = 0;

        // m ∈ {0, 1}. Pour des plages > 30j, BillingCalculator découpe
        // déjà par mois civil donc daysUsed ≤ 31, m=2 n'a pas de sens.
        for ($m = 0; $m <= 1; $m++) {
            $remainAfterMonth = max(0, $daysUsed - $m * self::DAYS_PER_MONTH_UNIT);

            // w ∈ {0..5} couvre tous les cas utiles (5×7 = 35 ≥ 31).
            for ($w = 0; $w <= 5; $w++) {
                $remainAfterWeek = max(0, $remainAfterMonth - $w * self::DAYS_PER_WEEK_UNIT);
                $d = $remainAfterWeek;

                $cost = $m * $monthlyCents + $w * $weeklyCents + $d * $dailyCents;

                if ($cost < $bestCost) {
                    $bestCost = $cost;
                    $bestMonths = $m;
                    $bestWeeks = $w;
                    $bestDays = $d;
                }
            }
        }

        return new self($bestMonths, $bestWeeks, $bestDays, $bestCost);
    }
}
