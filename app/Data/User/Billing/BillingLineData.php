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
 * client) et **non** le découpage civil · d'où la cohabitation avec
 * `daysUsed` qui, lui, est le brut métier (jours réellement utilisés
 * dans le mois).
 *
 * **Sémantique réductions commerciales (Lot 2)** ·
 *   - `totalCents` représente désormais le NET (brut moins réduction).
 *     Sémantique préservée pour 100 % des consommateurs existants : ils
 *     affichent le montant final à payer, comme avant.
 *   - `grossTotalCents` expose le BRUT avant réduction (utile UI billing
 *     détaillée). En l'absence de réduction, `grossTotalCents == totalCents`.
 *   - `discountCents` = `grossTotalCents - totalCents`.
 *   - `appliedDiscountId` référence la `RentalDiscount` appliquée (null si
 *     aucune). Permet le drill-down depuis Invoice Show vers la fiche
 *     réduction.
 *   - `usedDates` exposé pour permettre au `DiscountApplier` de calculer
 *     le prorata partiel jour par jour. Conservé même après application
 *     pour traçabilité côté audit (négligeable en payload : ~30 strings
 *     par ligne pour un mois plein).
 */
#[TypeScript]
final class BillingLineData extends Data
{
    /**
     * @param  list<string>  $usedDates  Dates ISO Y-m-d où ce véhicule est
     *                                   effectivement utilisé pour cette
     *                                   entreprise sur le mois civil (déduit
     *                                   du clipping `expandContractsByKey`).
     *                                   Vide pour les lignes pré-Lot 2 ou
     *                                   les snapshot InvoiceLine.
     */
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
         * Montant NET ligne · `grossTotalCents - discountCents`.
         * Sémantiquement = ce que paie l'entreprise pour cette ligne.
         */
        public int $totalCents,
        /**
         * Montant BRUT ligne · `monthsBilled × monthlyRateCents +
         * weeksBilled × weeklyRateCents + daysBilled × dailyRateCents`.
         * En l'absence de réduction, identique à `totalCents`.
         */
        public int $grossTotalCents = 0,
        /**
         * Réduction commerciale appliquée à cette ligne (cents). 0 si
         * pas de réduction active.
         */
        public int $discountCents = 0,
        /**
         * Référence vers la `RentalDiscount` appliquée (null si aucune).
         */
        public ?int $appliedDiscountId = null,
        /**
         * Basis points appliqués (snapshot pour UI). Null si pas de
         * réduction.
         */
        public ?int $appliedDiscountBasisPoints = null,
        /**
         * Label snapshot de la réduction (pour tooltip UI sans 2e
         * lookup). Null si pas de réduction.
         */
        public ?string $appliedDiscountLabel = null,
        public array $usedDates = [],
    ) {}
}
