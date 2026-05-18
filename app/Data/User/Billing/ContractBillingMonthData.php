<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une cellule du tableau récap facturation contrat · un mois civil.
 *
 * Si le tarif annuel n'est pas défini sur le véhicule pour l'année
 * concernée, `totalCents` vaut `null` et `hasMissingPricing = true`.
 */
#[TypeScript]
final class ContractBillingMonthData extends Data
{
    public function __construct(
        public int $year,
        public int $month,
        /** Nombre de jours du contrat dans ce mois civil (intersection). */
        public int $daysInMonth,
        public ?int $totalCents,
        public bool $hasMissingPricing,
        /**
         * Montant BRUT du mois (= avant réduction commerciale).
         * `null` si tarif manquant. Égal à `totalCents` sans réduction
         * (Lot 2 réductions commerciales).
         */
        public ?int $grossTotalCents = null,
        /**
         * Réduction commerciale appliquée au mois (cents). `null` si
         * tarif manquant, `0` si pas de réduction.
         */
        public ?int $discountCents = null,
    ) {}
}
