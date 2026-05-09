<?php

declare(strict_types=1);

namespace App\Contracts\Pdf;

use App\Fiscal\ValueObjects\DeclarationRenderContext;

/**
 * Rendu du PDF annexe documentaire d'une déclaration fiscale (Phase 11
 * D5, ADR-0015 § D6).
 *
 * **Signature D5.5 (rev. 1.1)** : prend un {@see DeclarationRenderContext}
 * complet (preview D3 + snapshot D5.2 + référence D5.3 + horodatage)
 * pour permettre au renderer d'afficher les vrais montants fiscaux post
 * decisions Requalified, la référence lisible `DECL-…`, et la trace
 * complète des décisions appliquées.
 *
 * Implémentations :
 *   - {@see App\Services\Pdf\BladeDomPdfDeclarationRenderer} : production
 *     (Blade + DomPDF, binding par défaut).
 *   - {@see App\Services\Pdf\NullDeclarationPdfRenderer} : stub minimal
 *     (utilisable pour tests qui veulent éviter le coût DomPDF).
 */
interface DeclarationPdfRendererInterface
{
    /**
     * Rend le PDF binary de la déclaration à partir de son contexte
     * de rendu complet.
     */
    public function render(DeclarationRenderContext $context): string;
}
