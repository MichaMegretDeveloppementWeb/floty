<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Data\User\FiscalDeclaration\DeclarationPreviewData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Implémentation stub D3 du renderer PDF déclaration fiscale.
 * Produit un PDF minimal valide (DomPDF + HTML inline) pour permettre
 * à `GenerateDeclarationAction` de fonctionner end-to-end avant la
 * livraison du vrai renderer en D5.
 *
 * La D5 substituera le binding vers un renderer DomPDF avec template
 * Blade complet (annexe documentaire ADR-0015 § D6) sans toucher à
 * `GenerateDeclarationAction` ni à ses tests.
 */
final readonly class NullDeclarationPdfRenderer implements DeclarationPdfRendererInterface
{
    public function render(DeclarationPreviewData $preview): string
    {
        $html = sprintf(
            <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Déclaration fiscale %s %d</title>
<style>
    body { font-family: sans-serif; padding: 2em; color: #1e293b; }
    h1 { font-size: 18pt; margin-bottom: 0.4em; }
    p { font-size: 11pt; line-height: 1.5; }
    .meta { color: #64748b; font-size: 10pt; }
    .stub { background: #fef3c7; padding: 0.8em; border-radius: 4px; }
</style>
</head>
<body>
<h1>Déclaration fiscale annexe · %s · %d</h1>
<p class="meta">Générée le %s</p>
<p class="stub">
    PDF stub Phase 11 D3. Le rendu complet (calcul détaillé,
    clusters, décisions, tableau d'audit) arrive en D5.
</p>
<p>Clusters détectés : %d. Décisions prises : %d.</p>
</body>
</html>
HTML,
            htmlspecialchars($preview->companyShortCode),
            $preview->fiscalYear,
            htmlspecialchars($preview->companyLegalName),
            $preview->fiscalYear,
            Carbon::now()->format('d/m/Y H:i'),
            count($preview->clusters),
            $this->countResolvedDecisions($preview),
        );

        return Pdf::loadHTML($html)->output();
    }

    /**
     * Nombre de clusters dont une décision a été prise (non null).
     */
    private function countResolvedDecisions(DeclarationPreviewData $preview): int
    {
        $count = 0;
        foreach ($preview->clusters as $cluster) {
            if ($cluster->decision !== null) {
                $count++;
            }
        }

        return $count;
    }
}
