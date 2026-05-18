<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Résultat du calcul de facture mensuelle pour une (entreprise × année
 * × mois) · N lignes véhicule + total HT.
 *
 * Produit par {@see App\Services\Billing\BillingCalculator::calculate}.
 * En cas de tarif manquant pour au moins un véhicule, le service lève
 * {@see App\Exceptions\Billing\MissingPricingException} **avant** de
 * retourner ce DTO ; donc une instance présente garantit l'exhaustivité
 * des tarifs.
 *
 * **Sémantique réductions commerciales (Lot 2)** ·
 *   - `totalCents` = NET (somme `lines[].totalCents`). Rétrocompat 100 %.
 *   - `grossTotalCents` = BRUT (somme `lines[].grossTotalCents`).
 *   - `totalDiscountCents` = somme des `lines[].discountCents`. Vaut 0
 *     en l'absence de réduction.
 */
#[TypeScript]
final class BillingCalculationData extends Data
{
    /**
     * @param  list<BillingLineData>  $lines  Une ligne par véhicule, triées
     *                                        par plaque pour stabilité d'affichage.
     */
    public function __construct(
        public int $companyId,
        public int $year,
        public int $month,
        #[DataCollectionOf(BillingLineData::class)]
        public array $lines,
        /** Somme des `lines[].totalCents` (= net). */
        public int $totalCents,
        /** Somme des `lines[].grossTotalCents` (= brut avant réduction). */
        public int $grossTotalCents = 0,
        /** Somme des `lines[].discountCents`. */
        public int $totalDiscountCents = 0,
    ) {}
}
