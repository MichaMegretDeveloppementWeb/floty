<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Data\User\FiscalDeclaration\FiscalDeclarationSnapshotData;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
use App\Services\Fiscal\SnapshotHashCalculator;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * HTML → PDF renderer for the fiscal declaration appendix.
 *
 * A4 portrait, DejaVu Sans (UTF-8 native in DomPDF), `display: table`
 * CSS (DomPDF supports neither flexbox nor grid). The companion
 * {@see renderHtml()} exposes the intermediate HTML so tests can assert
 * on content without parsing the PDF binary. Mirrors
 * {@see App\Services\Invoice\InvoicePdfRenderer}.
 */
final readonly class BladeDomPdfDeclarationRenderer implements DeclarationPdfRendererInterface
{
    public function render(DeclarationRenderContext $context): string
    {
        $pdf = Pdf::loadView('pdf.fiscal-declaration', $this->prepareViewData($context))
            ->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Renders only the intermediate HTML (no DomPDF pass) · useful for
     * content tests and visual debugging.
     */
    public function renderHtml(DeclarationRenderContext $context): string
    {
        return view('pdf.fiscal-declaration', $this->prepareViewData($context))->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareViewData(DeclarationRenderContext $context): array
    {
        $snapshot = $context->snapshot;

        // Hash must match exactly the one computed on the Show page
        // from the persisted JSON payload · canonicalise through the
        // same Spatie Data serialisation used by
        // GenerateDeclarationAction when storing
        // `generated_snapshot_payload`.
        $canonicalPayload = FiscalDeclarationSnapshotData::fromValueObject($snapshot)->toArray();

        return [
            'reference' => $context->reference,
            'generatedAtLabel' => $context->generatedAt->format('d/m/Y H:i'),
            'companyShortCode' => $snapshot->companyShortCode,
            'companyLegalName' => $snapshot->companyLegalName,
            'companyAddressLines' => $this->splitAddressLines($snapshot->companyAddress),
            'fiscalYear' => $snapshot->fiscalYear,
            // CO₂ and pollutant components are detail figures (centime,
            // 2 decimals). `totalDue` is the declaratory amount,
            // already rounded half-up to the euro by
            // `DeclarationFiscalEngine` (CIBS L. 131-1), so we render
            // it without decimals for visual consistency.
            'co2DueTotal' => $this->formatEuros($snapshot->co2DueTotal),
            'pollutantsDueTotal' => $this->formatEuros($snapshot->pollutantsDueTotal),
            'totalDue' => $this->formatEuros($snapshot->totalDue, 0),
            'contractRows' => $this->buildContractRows($snapshot->contractBreakdown),
            'snapshotHash' => SnapshotHashCalculator::compute($canonicalPayload),
        ];
    }

    /**
     * Splits the snapshot's multi-line address into `list<string>` so
     * the Blade view can iterate without parsing newlines itself.
     *
     * @return list<string>
     */
    private function splitAddressLines(?string $address): array
    {
        if ($address === null || $address === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $address)),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * Iterates the breakdown sorted by `(vehicleLabel, startDate)` and
     * produces a flat row list ready for display. The official PDF
     * carries no internal review annotations (cluster headers, risk
     * levels, decisions, justifications) · only the per-contract
     * detail with its final fiscal treatment. The « Type » column
     * (LCD/LLD) was removed · it adds noise without value for the
     * administration; exempted contracts get a compact mention with
     * the CIBS article instead.
     *
     * @param  list<ContractSnapshotEntry>  $contractBreakdown
     * @return list<array{
     *     period: string,
     *     vehicleLabel: string,
     *     vehicleFiscalSummary: string,
     *     daysInYearAssigned: int,
     *     totalDue: string,
     *     exemptionReason: ?string,
     * }>
     */
    private function buildContractRows(array $contractBreakdown): array
    {
        $rows = [];
        foreach ($contractBreakdown as $entry) {
            $rows[] = [
                'period' => $this->formatPeriod($entry->startDate, $entry->endDate),
                'vehicleLabel' => $entry->vehicleLabel,
                'vehicleFiscalSummary' => $entry->vehicleFiscalSummary,
                'daysInYearAssigned' => $entry->daysInYearAssigned,
                'totalDue' => $this->formatEuros($entry->totalDue),
                'exemptionReason' => $entry->exemptionReason,
            ];
        }

        return $rows;
    }

    private function formatPeriod(string $startDate, string $endDate): string
    {
        return sprintf(
            '%s → %s',
            $this->formatDateFr($startDate),
            $this->formatDateFr($endDate),
        );
    }

    private function formatDateFr(string $isoDate): string
    {
        $parts = explode('-', $isoDate);
        if (count($parts) !== 3) {
            return $isoDate;
        }

        return sprintf('%s/%s/%s', $parts[2], $parts[1], $parts[0]);
    }

    /**
     * Formats an amount in euros for the official PDF ·
     *
     *   - comma as decimal separator (« 1 234,56 »)
     *   - narrow non-breaking space U+202F as thousands separator AND
     *     before the `€` symbol (« 1 234,56 € »)
     *   - configurable fraction digits · 2 for line items and
     *     components, 0 for the declaratory total (CIBS L. 131-1
     *     half-up rounding to the euro)
     *
     * U+202F (NARROW NO-BREAK SPACE) follows the official French
     * typography (Lexique des règles typographiques en usage à
     * l'Imprimerie nationale + Unicode UAX #14). It forbids line
     * breaks between the number and the symbol and provides tighter
     * spacing than the standard non-breaking space U+00A0. DomPDF
     * supports Unicode codepoints natively through the embedded
     * DejaVu Sans font. The codepoint is documented to prevent future
     * refactors from replacing it with an ASCII space (line-break
     * risk) or U+00A0 (renders too wide).
     */
    private function formatEuros(float $amount, int $fractionDigits = 2): string
    {
        $formatted = number_format($amount, $fractionDigits, ',', "\u{202F}");

        return $formatted."\u{202F}€";
    }
}
