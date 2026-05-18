<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Data\User\Billing\BillingCalculationData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Rendu HTML → PDF d'une facture mensuelle Floty (Phase 14.E V1.2).
 *
 * Reçoit en entrée :
 *   - le résultat brut de calcul ({@see BillingCalculationData})
 *   - les métadonnées entreprise destinataire (nom, SIREN, ville)
 *   - les métadonnées émetteur (Phase 14.G : lues depuis la table
 *     `billing_settings`, alimentées par la page Paramètres)
 *   - le numéro de facture pré-attribué et la date d'émission
 *
 * Retourne le binaire PDF (le caller `GenerateInvoiceAction` persiste
 * via {@see InvoicePdfStorage}).
 *
 * **Format des lignes** : on transmet à la vue Blade des chaînes déjà
 * formatées en €, format FR (ex. `1 800,00 €`). Le service centralise
 * le formatage pour éviter sa duplication dans le template.
 */
final readonly class InvoicePdfRenderer
{
    /**
     * @param  array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}  $issuer
     * @param  array{legalName: string, siren?: string|null, city?: string|null}  $company
     */
    public function render(
        BillingCalculationData $calculation,
        array $issuer,
        array $company,
        string $invoiceNumber,
        Carbon $generatedAt,
    ): string {
        $lines = array_map(
            fn ($line): array => [
                'vehicleLabel' => sprintf(
                    '%s %s %s',
                    $line->licensePlate,
                    $line->brand,
                    $line->model,
                ),
                'daysUsed' => $line->daysUsed,
                'monthsBilled' => $line->monthsBilled,
                'weeksBilled' => $line->weeksBilled,
                'daysBilled' => $line->daysBilled,
                'monthlyRate' => $this->formatEuros($line->monthlyRateCents),
                'weeklyRate' => $this->formatEuros($line->weeklyRateCents),
                'dailyRate' => $this->formatEuros($line->dailyRateCents),
                'totalLabel' => $this->formatEuros($line->totalCents),
                // Lot 3 réductions commerciales · snapshot brut/réduction
                // par ligne pour affichage sous la décomposition tarifaire
                // quand une réduction a été appliquée.
                'grossTotalLabel' => $this->formatEuros($line->grossTotalCents),
                'hasDiscount' => $line->discountCents > 0,
                'discountLabel' => $line->discountCents > 0 ? $this->formatEuros($line->discountCents) : null,
                'discountPercentLabel' => $line->appliedDiscountBasisPoints !== null
                    ? $this->formatPercent($line->appliedDiscountBasisPoints)
                    : null,
                'discountName' => $line->appliedDiscountLabel,
            ],
            $calculation->lines,
        );

        $periodLabel = $this->frenchMonthLabel($calculation->month).' '.$calculation->year;
        $generatedAtLabel = $generatedAt->format('d/m/Y');
        $totalLabel = $this->formatEuros($calculation->totalCents);
        $hasDiscount = $calculation->totalDiscountCents > 0;
        $grossTotalLabel = $this->formatEuros($calculation->grossTotalCents);
        $totalDiscountLabel = $hasDiscount ? $this->formatEuros($calculation->totalDiscountCents) : null;

        $pdf = Pdf::loadView('invoices.monthly', [
            'invoiceNumber' => $invoiceNumber,
            'issuer' => $issuer,
            'company' => $company,
            'periodLabel' => $periodLabel,
            'generatedAtLabel' => $generatedAtLabel,
            'lines' => $lines,
            'totalLabel' => $totalLabel,
            'hasDiscount' => $hasDiscount,
            'grossTotalLabel' => $grossTotalLabel,
            'totalDiscountLabel' => $totalDiscountLabel,
        ])->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    private function formatEuros(int $cents): string
    {
        $euros = $cents / 100;
        $formatted = number_format($euros, 2, ',', "\u{202F}");

        return $formatted."\u{202F}€";
    }

    /**
     * Format un pourcentage en basis points · convention FR (`,`
     * décimal, NNBSP avant `%`). Élide les zéros terminaux (`10 %`
     * plutôt que `10,00 %`).
     */
    private function formatPercent(int $basisPoints): string
    {
        $percent = $basisPoints / 100;

        // 2 décimales max, mais élision des zéros (10,5 et pas 10,50,
        // 10 et pas 10,00).
        if (fmod($percent, 1.0) === 0.0) {
            $formatted = number_format($percent, 0, ',', '');
        } else {
            $formatted = rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',');
        }

        return $formatted."\u{202F}%";
    }

    private function frenchMonthLabel(int $month): string
    {
        return [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ][$month] ?? '?';
    }
}
