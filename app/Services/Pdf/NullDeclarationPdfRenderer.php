<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renderer PDF stub minimal pour les tests qui veulent éviter le coût
 * complet du Blade renderer + tout le DOM CSS associé (Phase 11 D5.5
 * post-swap).
 *
 * En production le binding par défaut est désormais
 * {@see BladeDomPdfDeclarationRenderer} (cf. {@see App\Providers\AppServiceProvider}).
 * Ce stub reste disponible pour les tests qui le bindent explicitement.
 *
 * Le PDF stub produit une page minimale avec la référence et les
 * totaux fiscaux du context, suffisant pour valider que le binary
 * sortant commence par `%PDF-` sans payer le rendu complet.
 */
final readonly class NullDeclarationPdfRenderer implements DeclarationPdfRendererInterface
{
    public function render(DeclarationRenderContext $context): string
    {
        $snapshot = $context->snapshot;
        $html = sprintf(
            <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Déclaration %s</title>
<style>
    body { font-family: sans-serif; padding: 2em; color: #1e293b; }
    h1 { font-size: 18pt; margin-bottom: 0.4em; }
    p { font-size: 11pt; line-height: 1.5; }
    .meta { color: #64748b; font-size: 10pt; }
    .stub { background: #fef3c7; padding: 0.8em; border-radius: 4px; }
</style>
</head>
<body>
<h1>Déclaration fiscale · %s</h1>
<p class="meta">Entreprise %s, exercice %d.</p>
<p class="meta">Total dû : %.2f €. Générée le %s.</p>
<p class="stub">PDF stub. Le rendu complet est produit par BladeDomPdfDeclarationRenderer.</p>
</body>
</html>
HTML,
            htmlspecialchars($context->reference),
            htmlspecialchars($context->reference),
            htmlspecialchars($snapshot->companyShortCode),
            $snapshot->fiscalYear,
            $snapshot->totalDue,
            $context->generatedAt->format('d/m/Y H:i'),
        );

        return Pdf::loadHTML($html)->output();
    }
}
