<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Data\User\Invoice\InvoiceDivergenceData;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Invoice;
use App\Services\Billing\BillingCalculator;

/**
 * Détecte la divergence entre une facture émise (snapshot figé) et la
 * réalité contractuelle actuelle (recalcul à l'instant T) — Phase 14.I+
 * V1.2.
 *
 * **Doctrine immuabilité** (cf. ADR-0008) : la facture émise ne change
 * jamais. Si un contrat est ajouté/modifié/supprimé sur le mois facturé
 * après émission, le recalcul actuel diverge du snapshot. Ce service
 * matérialise la comparaison pour exposer un signal UI clair (« Données
 * obsolètes ») et permettre la régénération sur demande explicite.
 *
 * **Coût** : un appel = un calcul `BillingCalculator::calculate` complet
 * pour le couple (entreprise × année × mois). Acceptable sur la fiche
 * Show (1 calcul) et sur le récap mensuel entreprise (jusqu'à 12 calculs
 * par page, déjà payés). Sur l'Index Invoices, on paie 1 calcul par
 * ligne paginée — acceptable pour V1.2 démo (max ~100 factures).
 */
final readonly class InvoiceDivergenceChecker
{
    public function __construct(
        private BillingCalculator $calculator,
    ) {}

    public function check(Invoice $invoice): InvoiceDivergenceData
    {
        // Snapshot figé : `total_ht_cents` est sur la facture, le total
        // jours utilisés est la somme des lignes (chargées via la
        // relation, à éager-loader par le caller pour éviter N+1).
        $invoicedDaysUsed = (int) $invoice->lines->sum('days_used');
        $invoicedTotalCents = (int) $invoice->total_ht_cents;

        try {
            $current = $this->calculator->calculate(
                companyId: $invoice->company_id,
                year: $invoice->year,
                month: $invoice->month,
            );

            $currentDaysUsed = (int) array_sum(array_map(
                static fn ($line) => $line->daysUsed,
                $current->lines,
            ));

            $hasDivergence = $currentDaysUsed !== $invoicedDaysUsed
                || $current->totalCents !== $invoicedTotalCents;

            return new InvoiceDivergenceData(
                hasDivergence: $hasDivergence,
                invoicedDaysUsed: $invoicedDaysUsed,
                invoicedTotalCents: $invoicedTotalCents,
                currentDaysUsed: $currentDaysUsed,
                currentTotalCents: $current->totalCents,
            );
        } catch (MissingPricingException) {
            // Tarif annuel manquant désormais (a été supprimé après
            // l'émission). On considère ça comme une divergence forte —
            // la facture ne peut plus être reproduite à l'identique.
            return new InvoiceDivergenceData(
                hasDivergence: true,
                invoicedDaysUsed: $invoicedDaysUsed,
                invoicedTotalCents: $invoicedTotalCents,
                currentDaysUsed: null,
                currentTotalCents: null,
            );
        }
    }
}
