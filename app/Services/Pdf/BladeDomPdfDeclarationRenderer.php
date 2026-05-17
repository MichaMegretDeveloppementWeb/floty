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
            // Lot 5 D15 · les composantes CO₂ et polluants sont des
            // informations détaillées (centime · 2 décimales). Le
            // `totalDue` est le montant à déclarer officiellement et
            // est arrondi half-up à l'EURO côté `DeclarationFiscalEngine`
            // (doctrine CIBS L. 131-1) · on l'affiche donc sans
            // décimales pour cohérence visuelle.
            'co2DueTotal' => $this->formatEuros($snapshot->co2DueTotal),
            'pollutantsDueTotal' => $this->formatEuros($snapshot->pollutantsDueTotal),
            'totalDue' => $this->formatEuros($snapshot->totalDue, 0),
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

    /**
     * Formate un montant en euros pour le PDF officiel ·
     *
     *   - virgule comme séparateur décimal (« 1 234,56 »)
     *   - **espace fine insécable U+202F** comme séparateur de milliers
     *     ET avant le symbole `€` (« 1 234,56 € »)
     *   - 2 décimales fixes
     *
     * Le caractère U+202F (NARROW NO-BREAK SPACE) est conforme à la
     * typographie française officielle (cf. Lexique des règles
     * typographiques en usage à l'Imprimerie nationale + Unicode UAX
     * #14). Il interdit la coupure de ligne entre nombre et symbole et
     * produit un espacement plus serré que l'espace insécable U+00A0
     * standard. DomPDF supporte nativement les codepoints Unicode via
     * la police DejaVu Sans embarquée. Lot 5 D10 (F-19D2-017) · doctrine
     * documentée pour éviter qu'une refonte ultérieure ne le remplace
     * par un espace ASCII (cassure ligne possible) ou U+00A0 (rendu
     * trop large).
     */
    /**
     * Lot 5 D15 · `$fractionDigits` configurable · 2 décimales (défaut)
     * pour les lignes et composantes, 0 décimales pour le montant à
     * déclarer (doctrine CIBS L. 131-1 · arrondi half-up à l'euro).
     */
    private function formatEuros(float $amount, int $fractionDigits = 2): string
    {
        $formatted = number_format($amount, $fractionDigits, ',', "\u{202F}");

        return $formatted."\u{202F}€";
    }
}
