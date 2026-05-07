<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une cellule du tableau récapitulatif 12 mois × {jours, montant} pour
 * une entité (véhicule ou entreprise) sur une année donnée.
 *
 * `totalCents` est `null` lorsqu'au moins un véhicule présent sur le
 * mois n'a pas de tarif annuel défini : on **ne peut pas** estimer le
 * montant correctement, donc on le marque explicitement comme
 * « facturable mais bloqué » via `hasMissingPricing = true`.
 *
 * `daysUsed` reste défini dans tous les cas — c'est de l'agrégation
 * pure de données métier qui n'a pas besoin des tarifs.
 *
 * `existingInvoiceId` / `existingInvoiceNumber` sont peuplés sur le
 * récap **fiche entreprise** quand une facture a déjà été émise pour
 * ce couple (entreprise × année × mois). L'UI bascule alors le bouton
 * « Générer » en lien « Voir #YYYY-MM-NNNN ». Toujours `null` sur le
 * récap véhicule (plusieurs entreprises peuvent émettre une facture
 * sur le même mois).
 *
 * `invoicedDaysUsed` / `invoicedTotalCents` exposent le **snapshot
 * figé** au moment de l'émission de la facture (jamais recalculé). La
 * comparaison avec `daysUsed` / `totalCents` (recalcul dynamique)
 * permet à l'UI de détecter une divergence et d'afficher un avertissement
 * « données ont changé depuis l'émission ». Toujours `null` quand
 * aucune facture n'existe pour ce mois.
 */
#[TypeScript]
final class MonthlyBreakdownEntryData extends Data
{
    public function __construct(
        public int $month,
        public int $daysUsed,
        public ?int $totalCents,
        public bool $hasMissingPricing,
        public ?int $existingInvoiceId = null,
        public ?string $existingInvoiceNumber = null,
        public ?int $invoicedDaysUsed = null,
        public ?int $invoicedTotalCents = null,
    ) {}
}
