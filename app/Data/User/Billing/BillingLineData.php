<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une ligne de facture mensuelle : un véhicule × un mois civil ×
 * une entreprise utilisatrice.
 *
 * Composée par {@see App\Services\Billing\BillingCalculator} en aval
 * de l'algorithme {@see App\Services\Billing\OptimalRateBreakdown}.
 * Les compteurs `monthsBilled` / `weeksBilled` / `daysBilled` reflètent
 * la **décomposition tarifaire** retenue (combo le moins cher pour le
 * client) et **non** le découpage civil — d'où la cohabitation avec
 * `daysUsed` qui, lui, est le brut métier (jours réellement utilisés
 * dans le mois).
 */
#[TypeScript]
final class BillingLineData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public string $brand,
        public string $model,
        /**
         * Jours d'utilisation effectifs (intersection contrats ∩ mois
         * civil), dédupliqués : si le même véhicule a deux contrats
         * pour la même entreprise sur le mois, les jours communs ne
         * sont comptés qu'une fois.
         */
        public int $daysUsed,
        public int $monthsBilled,
        public int $weeksBilled,
        public int $daysBilled,
        public int $dailyRateCents,
        public int $weeklyRateCents,
        public int $monthlyRateCents,
        /**
         * `monthsBilled × monthlyRateCents + weeksBilled × weeklyRateCents
         *  + daysBilled × dailyRateCents`.
         */
        public int $totalCents,
    ) {}
}
