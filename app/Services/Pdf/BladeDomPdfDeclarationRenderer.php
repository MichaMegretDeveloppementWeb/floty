<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
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

        // Phase 11 D5.8.1 · agrégation contrat → véhicule pour préserver
        // le template PDF actuel (refonte par contrat livrée en D5.8.7).
        $vehicleRows = $this->aggregateContractsByVehicle($snapshot->contractBreakdown);

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

    /**
     * Agrège le breakdown par contrat (D5.8.1) en vue véhicule pour
     * préserver le rendu PDF actuel le temps du refactor template
     * (D5.8.7 livrera la refonte par contrat avec clusters in-line).
     *
     * @param  list<ContractSnapshotEntry>  $contractBreakdown
     * @return list<array{label: string, daysAssigned: int, co2Due: string, pollutantsDue: string, totalDue: string}>
     */
    private function aggregateContractsByVehicle(array $contractBreakdown): array
    {
        $byVehicle = [];
        foreach ($contractBreakdown as $entry) {
            $vehicleId = $entry->vehicleId;
            if (! isset($byVehicle[$vehicleId])) {
                $byVehicle[$vehicleId] = [
                    'label' => $entry->vehicleLabel,
                    'daysAssigned' => 0,
                    'co2DueRaw' => 0.0,
                    'pollutantsDueRaw' => 0.0,
                    'totalDueRaw' => 0.0,
                ];
            }
            $byVehicle[$vehicleId]['daysAssigned'] += $entry->daysInYearAssigned;
            $byVehicle[$vehicleId]['co2DueRaw'] += $entry->co2Due;
            $byVehicle[$vehicleId]['pollutantsDueRaw'] += $entry->pollutantsDue;
            $byVehicle[$vehicleId]['totalDueRaw'] += $entry->totalDue;
        }

        return array_values(array_map(
            fn (array $agg): array => [
                'label' => $agg['label'],
                'daysAssigned' => $agg['daysAssigned'],
                'co2Due' => $this->formatEuros(round($agg['co2DueRaw'], 2, PHP_ROUND_HALF_UP)),
                'pollutantsDue' => $this->formatEuros(round($agg['pollutantsDueRaw'], 2, PHP_ROUND_HALF_UP)),
                'totalDue' => $this->formatEuros(round($agg['totalDueRaw'], 2, PHP_ROUND_HALF_UP)),
            ],
            $byVehicle,
        ));
    }
}
