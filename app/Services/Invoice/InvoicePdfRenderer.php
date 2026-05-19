<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Data\User\Billing\BillingCalculationData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * HTML → PDF renderer for a Floty monthly invoice.
 *
 * Takes the raw {@see BillingCalculationData}, the recipient company
 * metadata (name, SIREN, city), the issuer metadata (read from the
 * `billing_settings` table fed by the settings page), the
 * pre-assigned invoice number and the emission date. Returns the PDF
 * binary; the caller `GenerateInvoiceAction` persists it through
 * {@see InvoicePdfStorage}.
 *
 * Line formatting · already-formatted FR euro strings (« 1 800,00 € »)
 * are passed to the Blade view. The renderer centralises formatting
 * to keep the template free of duplication.
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
                // Per-line gross + discount snapshot for the
                // discount breakdown shown below the rate split when
                // a discount applied.
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
     * Formats basis points as an FR percentage (`,` decimal, NNBSP
     * before `%`). Trailing zeros are elided (« 10 % » rather than
     * « 10,00 % »).
     */
    private function formatPercent(int $basisPoints): string
    {
        $percent = $basisPoints / 100;

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
