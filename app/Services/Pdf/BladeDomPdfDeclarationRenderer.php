<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
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

        return [
            'reference' => $context->reference,
            'generatedAtLabel' => $context->generatedAt->format('d/m/Y H:i'),
            'companyShortCode' => $snapshot->companyShortCode,
            'companyLegalName' => $snapshot->companyLegalName,
            'fiscalYear' => $snapshot->fiscalYear,
            'co2DueTotal' => $this->formatEuros($snapshot->co2DueTotal),
            'pollutantsDueTotal' => $this->formatEuros($snapshot->pollutantsDueTotal),
            'totalDue' => $this->formatEuros($snapshot->totalDue),
            'contractRows' => $this->buildContractRows($snapshot->contractBreakdown),
            'optOutContractsCount' => count($snapshot->optOutContractIds),
        ];
    }

    /**
     * Itère sur le breakdown trié `(vehicleLabel, startDate)` et produit
     * une liste de rows prête à l'affichage : chaque row porte
     * `clusterHeader` rempli uniquement quand elle ouvre un nouveau
     * cluster (premier contrat consécutif partageant le `fingerprint`).
     * Cette structure permet au template Blade de rendre le header en
     * une ligne séparée (bordure + agrégats) sans recalcul côté vue.
     *
     * @param  list<ContractSnapshotEntry>  $contractBreakdown
     * @return list<array{
     *     period: string,
     *     contractTypeLabel: string,
     *     vehicleLabel: string,
     *     vehicleFiscalSummary: string,
     *     daysInYearAssigned: int,
     *     totalDue: string,
     *     isInCluster: bool,
     *     clusterRiskLevel: ?string,
     *     clusterDecisionRetained: bool,
     *     clusterHeader: ?array{
     *         codeLabel: string,
     *         levelLabel: string,
     *         levelClass: string,
     *         contractsCount: int,
     *         cumulativeDaysInYear: int,
     *         decisionLabel: ?string,
     *         decisionClass: ?string,
     *         justification: ?string,
     *     }
     * }>
     */
    private function buildContractRows(array $contractBreakdown): array
    {
        $clusterAggregates = $this->aggregateClustersByFingerprint($contractBreakdown);

        $rows = [];
        $previousFingerprint = null;
        foreach ($contractBreakdown as $entry) {
            $fingerprint = $entry->clusterFingerprint;
            $isFirstInCluster = $fingerprint !== null
                && $fingerprint !== $previousFingerprint;

            $clusterHeader = null;
            if ($isFirstInCluster) {
                $aggregate = $clusterAggregates[$fingerprint];
                $clusterHeader = [
                    'codeLabel' => $this->codeLabel($entry->clusterRiskCode),
                    'levelLabel' => $this->levelLabel($entry->clusterRiskLevel),
                    'levelClass' => $this->levelClass($entry->clusterRiskLevel),
                    'contractsCount' => $aggregate['contractsCount'],
                    'cumulativeDaysInYear' => $aggregate['cumulativeDaysInYear'],
                    'decisionLabel' => $this->decisionLabel($entry->clusterDecision),
                    'decisionClass' => $this->decisionClass($entry->clusterDecision),
                    'justification' => $entry->clusterJustification,
                ];
            }

            $rows[] = [
                'period' => $this->formatPeriod($entry->startDate, $entry->endDate),
                'contractTypeLabel' => $entry->contractType === ContractType::Lcd ? 'LCD' : 'LLD',
                'vehicleLabel' => $entry->vehicleLabel,
                'vehicleFiscalSummary' => $entry->vehicleFiscalSummary,
                'daysInYearAssigned' => $entry->daysInYearAssigned,
                'totalDue' => $this->formatEuros($entry->totalDue),
                'isInCluster' => $fingerprint !== null,
                'clusterRiskLevel' => $entry->clusterRiskLevel?->value,
                'clusterDecisionRetained' => $entry->clusterDecisionRetainedFrom !== null,
                'clusterHeader' => $clusterHeader,
            ];

            $previousFingerprint = $fingerprint;
        }

        return $rows;
    }

    /**
     * @param  list<ContractSnapshotEntry>  $contractBreakdown
     * @return array<string, array{contractsCount: int, cumulativeDaysInYear: int}>
     */
    private function aggregateClustersByFingerprint(array $contractBreakdown): array
    {
        $aggregates = [];
        foreach ($contractBreakdown as $entry) {
            $fingerprint = $entry->clusterFingerprint;
            if ($fingerprint === null) {
                continue;
            }
            if (! isset($aggregates[$fingerprint])) {
                $aggregates[$fingerprint] = [
                    'contractsCount' => 0,
                    'cumulativeDaysInYear' => 0,
                ];
            }
            $aggregates[$fingerprint]['contractsCount']++;
            $aggregates[$fingerprint]['cumulativeDaysInYear'] += $entry->daysInYearAssigned;
        }

        return $aggregates;
    }

    private function codeLabel(?RiskCode $code): string
    {
        return match ($code) {
            RiskCode::ChainFort => 'Chaîne LCD forte',
            RiskCode::Chain => 'Chaîne LCD',
            null => '',
        };
    }

    private function levelLabel(?RiskLevel $level): string
    {
        return match ($level) {
            RiskLevel::Eleve => 'Risque élevé',
            RiskLevel::Moyen => 'Risque moyen',
            null => '',
        };
    }

    private function levelClass(?RiskLevel $level): string
    {
        return match ($level) {
            RiskLevel::Eleve => 'level-eleve',
            RiskLevel::Moyen => 'level-moyen',
            null => '',
        };
    }

    private function decisionLabel(?ReviewDecisionType $decision): ?string
    {
        return match ($decision) {
            ReviewDecisionType::Requalified => 'Requalifié',
            ReviewDecisionType::Conserved => 'Conservé',
            null => null,
        };
    }

    private function decisionClass(?ReviewDecisionType $decision): ?string
    {
        return match ($decision) {
            ReviewDecisionType::Requalified => 'pill-requalified',
            ReviewDecisionType::Conserved => 'pill-conserved',
            null => null,
        };
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
