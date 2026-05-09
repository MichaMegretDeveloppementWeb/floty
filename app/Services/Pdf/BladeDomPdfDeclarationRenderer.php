<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
use App\Fiscal\ValueObjects\VehicleSnapshotEntry;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Rendu HTML → PDF de l'annexe documentaire d'une déclaration fiscale
 * (Phase 11 D5.4, ADR-0015 § D6).
 *
 * Service production qui remplacera le {@see NullDeclarationPdfRenderer}
 * stub en D5.5 (swap binding + refonte interface). Pour l'instant,
 * livré en standalone et testable en isolation.
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

        $vehicleRows = array_map(
            fn (VehicleSnapshotEntry $entry): array => [
                'label' => $entry->vehicleLabel,
                'daysAssigned' => $entry->daysAssigned,
                'co2Due' => $this->formatEuros($entry->co2Due),
                'pollutantsDue' => $this->formatEuros($entry->pollutantsDue),
                'totalDue' => $this->formatEuros($entry->totalDue),
            ],
            $snapshot->vehicleBreakdown,
        );

        $decisionRows = array_map(
            fn (AppliedDecisionEntry $entry): array => [
                'fingerprintShort' => substr($entry->clusterFingerprint, 0, 12),
                'riskCodeLabel' => $entry->riskCode->value,
                'decisionLabel' => $entry->decision->value,
                'contractsCount' => count($entry->contractIds),
                'justification' => $entry->justification,
            ],
            $snapshot->appliedDecisions,
        );

        return [
            'reference' => $context->reference,
            'generatedAtLabel' => $context->generatedAt->format('d/m/Y H:i'),
            'companyShortCode' => $snapshot->companyShortCode,
            'companyLegalName' => $snapshot->companyLegalName,
            'fiscalYear' => $snapshot->fiscalYear,
            'co2DueTotal' => $this->formatEuros($snapshot->co2DueTotal),
            'pollutantsDueTotal' => $this->formatEuros($snapshot->pollutantsDueTotal),
            'totalDue' => $this->formatEuros($snapshot->totalDue),
            'vehicleRows' => $vehicleRows,
            'decisionRows' => $decisionRows,
            'optOutContractsCount' => count($snapshot->optOutContractIds),
        ];
    }

    private function formatEuros(float $amount): string
    {
        $formatted = number_format($amount, 2, ',', "\u{202F}");

        return $formatted."\u{202F}€";
    }
}
