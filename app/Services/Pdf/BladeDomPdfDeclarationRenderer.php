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
 * Rendu HTML → PDF de l'annexe documentaire d'une déclaration fiscale
 * (Phase 11 D5.4, refondu D5.8.5 avec breakdown par contrat et
 * clusters LCD groupés visuellement).
 *
 * **Format PDF** : A4 portrait, police DejaVu Sans (UTF-8 native
 * DomPDF), CSS basé sur `display: table` (DomPDF ne supporte pas
 * flexbox/grid).
 *
 * **Méthode séparée `renderHtml()`** : expose le HTML intermédiaire
 * (avant DomPDF) pour faciliter les tests de contenu sans avoir à
 * parser le PDF binaire.
 *
 * Pattern aligné sur {@see App\Services\Invoice\InvoicePdfRenderer}.
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
     * Rend uniquement le HTML intermédiaire (sans passage DomPDF). Utile
     * pour les tests de contenu et l'éventuel debug visuel.
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

        // Le hash doit matcher exactement celui calculé côté Show depuis le
        // payload JSON persisté en BDD · on canonise via la même
        // sérialisation Spatie Data utilisée par
        // GenerateDeclarationAction lors de l'enregistrement de
        // `generated_snapshot_payload`.
        $canonicalPayload = FiscalDeclarationSnapshotData::fromValueObject($snapshot)->toArray();

        return [
            'reference' => $context->reference,
            'generatedAtLabel' => $context->generatedAt->format('d/m/Y H:i'),
            'companyShortCode' => $snapshot->companyShortCode,
            'companyLegalName' => $snapshot->companyLegalName,
            'companyAddressLines' => $this->splitAddressLines($snapshot->companyAddress),
            'fiscalYear' => $snapshot->fiscalYear,
            'co2DueTotal' => $this->formatEuros($snapshot->co2DueTotal),
            'pollutantsDueTotal' => $this->formatEuros($snapshot->pollutantsDueTotal),
            'totalDue' => $this->formatEuros($snapshot->totalDue),
            'contractRows' => $this->buildContractRows($snapshot->contractBreakdown),
            'snapshotHash' => SnapshotHashCalculator::compute($canonicalPayload),
        ];
    }

    /**
     * Coupe l'adresse multi-lignes du snapshot en `list<string>` pour
     * que la Blade itère sans avoir à parser le saut de ligne elle-même.
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
     * Itère sur le breakdown trié `(vehicleLabel, startDate)` et produit
     * une liste plate de rows prête à l'affichage. Phase 13 D5.10.J · le
     * PDF officiel ne porte plus les annotations de revue interne
     * (cluster headers, niveaux de risque, décisions, justifications) ·
     * uniquement le détail par contrat avec son traitement fiscal final
     * déjà appliqué.
     *
     * Phase 13 D5.10.W · la colonne « Type » (LCD/LLD) est retirée :
     * elle ajoute du bruit sans valeur pour l'administration, qui
     * lit uniquement le montant taxé et la période. À sa place, les
     * contrats exonérés affichent une mention compacte sous le
     * véhicule indiquant le motif et l'article CIBS associé.
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

    private function formatEuros(float $amount): string
    {
        $formatted = number_format($amount, 2, ',', "\u{202F}");

        return $formatted."\u{202F}€";
    }
}
