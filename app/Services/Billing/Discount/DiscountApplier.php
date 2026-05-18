<?php

declare(strict_types=1);

namespace App\Services\Billing\Discount;

use App\Data\User\Billing\BillingCalculationData;
use App\Data\User\Billing\BillingLineData;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\RentalDiscount;

/**
 * Applique les réductions commerciales actives sur un résultat de calcul
 * facture mensuelle. Service stateless · doctrine "branche d'équivalence
 * stricte" (cf. mémoire `feedback_conditional_perf_branch_equivalence`) ·
 *
 * - Si l'index est vide (cas dominant 99 % des entreprises sans réduction),
 *   retour `$calc` tel quel (zéro modification, zéro allocation, zéro
 *   différence de sérialisation).
 * - Si une réduction couvre tous les `usedDates` d'une ligne, prorata = 1.
 * - Si elle ne couvre qu'une partie, prorata = `discountedDays / daysUsed`.
 *
 * **Algorithme du prorata partiel** ·
 *
 * Pour chaque ligne :
 *   1. Itère sur `usedDates`, demande au resolver `findFor(vehicleId, date)`.
 *   2. Compte les jours où une réduction est applicable (regroupés par
 *      `RentalDiscount.id` pour éviter le mélange).
 *   3. Si une seule réduction couvre des jours (cas dominant garanti par
 *      le ConflictService · pas de chevauchement sur intersection
 *      véhicules), calcule `discount = round(grossTotalCents *
 *      discountedDays / daysUsed * basisPoints / 10000)`.
 *   4. Met à jour `discountCents`, `totalCents`, `appliedDiscount*`.
 *
 * **Caveat documenté** · le prorata moyenne le coût/jour, alors que le
 * combo `OptimalRateBreakdown` donne des coûts/jour variables (un jour
 * dans un forfait mois coûte moins/jour qu'un jour à l'unité). C'est
 * l'approche standard d'un % de remise · simple, prévisible, explicable
 * au client. Voir doc du plan `moonlit-hatching-mist.md`.
 */
final readonly class DiscountApplier
{
    /**
     * Variante batch · applique sur tous les mois d'un `calculateYear()`.
     * Évite de redéclarer le `ResolvedDiscountIndex` 12 fois.
     *
     * @param  array<int, BillingCalculationData|MissingPricingException>  $monthlyResults
     * @return array<int, BillingCalculationData|MissingPricingException>
     */
    public function applyToMonthlyResults(array $monthlyResults, ResolvedDiscountIndex $index): array
    {
        // Équivalence stricte · pas de réduction = passe-plat byte
        // identique.
        if ($index->isEmpty()) {
            return $monthlyResults;
        }

        $applied = [];
        foreach ($monthlyResults as $month => $result) {
            if ($result instanceof MissingPricingException) {
                $applied[$month] = $result;

                continue;
            }
            $applied[$month] = $this->applyWithIndex($result, $index);
        }

        return $applied;
    }

    /**
     * Variante mono-mois · pour `GenerateInvoiceAction` ou un appel à
     * `BillingCalculator::calculate()` isolé. Précharge l'index pour
     * la (companyId, year) du calcul.
     *
     * @param  callable(int $companyId, int $year): ResolvedDiscountIndex  $indexLoader
     */
    public function applyToCalculation(
        BillingCalculationData $calc,
        callable $indexLoader,
    ): BillingCalculationData {
        $index = $indexLoader($calc->companyId, $calc->year);

        if ($index->isEmpty()) {
            return $calc;
        }

        return $this->applyWithIndex($calc, $index);
    }

    /**
     * Cœur du calcul. Reconstruit un `BillingCalculationData` immuable
     * avec les lignes mises à jour. Spatie Data n'autorisant pas la
     * mutation directe (final readonly), on recompose.
     */
    public function applyWithIndex(
        BillingCalculationData $calc,
        ResolvedDiscountIndex $index,
    ): BillingCalculationData {
        if ($index->isEmpty()) {
            return $calc;
        }

        $newLines = [];
        $totalNet = 0;
        $totalGross = 0;
        $totalDiscount = 0;

        foreach ($calc->lines as $line) {
            $newLine = $this->applyToLine($line, $index);
            $newLines[] = $newLine;
            $totalNet += $newLine->totalCents;
            $totalGross += $newLine->grossTotalCents;
            $totalDiscount += $newLine->discountCents;
        }

        return new BillingCalculationData(
            companyId: $calc->companyId,
            year: $calc->year,
            month: $calc->month,
            lines: $newLines,
            totalCents: $totalNet,
            grossTotalCents: $totalGross,
            totalDiscountCents: $totalDiscount,
        );
    }

    /**
     * Calcule la réduction pour une ligne donnée. Cas particuliers ·
     * - `daysUsed === 0` ou `grossTotalCents === 0` · pas de réduction
     *   possible (ligne vide).
     * - `usedDates === []` · ligne pre-Lot 2 ou snapshot · pas de prorata
     *   possible, on retourne la ligne inchangée.
     */
    private function applyToLine(BillingLineData $line, ResolvedDiscountIndex $index): BillingLineData
    {
        if ($line->daysUsed === 0 || $line->grossTotalCents === 0 || $line->usedDates === []) {
            return $line;
        }

        // Groupement des jours réduits par RentalDiscount (au cas où
        // plusieurs réductions matchent · le ConflictService garantit
        // que c'est exclu en réalité, mais on reste défensif).
        /** @var array<int, array{discount: RentalDiscount, days: int}> $byDiscount */
        $byDiscount = [];

        foreach ($line->usedDates as $date) {
            $discount = $index->findFor($line->vehicleId, $date);
            if ($discount === null) {
                continue;
            }
            $id = (int) $discount->id;
            if (! isset($byDiscount[$id])) {
                $byDiscount[$id] = ['discount' => $discount, 'days' => 0];
            }
            $byDiscount[$id]['days']++;
        }

        if ($byDiscount === []) {
            return $line;
        }

        // Calcul du discount total · somme des prorata par réduction.
        $totalDiscountCents = 0;
        $appliedDiscount = null;
        $appliedDays = 0;

        foreach ($byDiscount as $entry) {
            /** @var RentalDiscount $discount */
            $discount = $entry['discount'];
            $days = $entry['days'];

            // Prorata · grossTotalCents × (days / daysUsed) × (bp / 10000)
            // Arrondi à l'entier le plus proche pour rester en cents (int).
            $cents = (int) round(
                $line->grossTotalCents * $days * $discount->discount_basis_points
                    / ($line->daysUsed * 10000),
            );

            $totalDiscountCents += $cents;

            // On capture la réduction "dominante" (celle qui couvre le
            // plus de jours) pour le snapshot ligne. Cas dominant unique
            // grâce au ConflictService.
            if ($appliedDiscount === null || $days > $appliedDays) {
                $appliedDiscount = $discount;
                $appliedDays = $days;
            }
        }

        // Clamp · jamais de réduction supérieure au brut (défense
        // contre les arrondis).
        if ($totalDiscountCents > $line->grossTotalCents) {
            $totalDiscountCents = $line->grossTotalCents;
        }

        // À ce stade `$appliedDiscount !== null` car `$byDiscount !== []`
        // a été vérifié plus haut · pas de nullsafe nécessaire (PHPStan
        // friendly).
        /** @var RentalDiscount $appliedDiscount */
        return new BillingLineData(
            vehicleId: $line->vehicleId,
            licensePlate: $line->licensePlate,
            brand: $line->brand,
            model: $line->model,
            daysUsed: $line->daysUsed,
            monthsBilled: $line->monthsBilled,
            weeksBilled: $line->weeksBilled,
            daysBilled: $line->daysBilled,
            dailyRateCents: $line->dailyRateCents,
            weeklyRateCents: $line->weeklyRateCents,
            monthlyRateCents: $line->monthlyRateCents,
            totalCents: $line->grossTotalCents - $totalDiscountCents,
            grossTotalCents: $line->grossTotalCents,
            discountCents: $totalDiscountCents,
            appliedDiscountId: $appliedDiscount->id,
            appliedDiscountBasisPoints: $appliedDiscount->discount_basis_points,
            appliedDiscountLabel: $appliedDiscount->label,
            usedDates: $line->usedDates,
        );
    }
}
