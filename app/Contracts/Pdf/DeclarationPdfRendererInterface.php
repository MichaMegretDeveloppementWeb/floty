<?php

declare(strict_types=1);

namespace App\Contracts\Pdf;

use App\Data\User\FiscalDeclaration\DeclarationPreviewData;

/**
 * Rendu du PDF annexe documentaire d'une déclaration fiscale
 * (Phase 11 D3, ADR-0015 § D6).
 *
 * Interface posée en D3 pour permettre à `GenerateDeclarationAction`
 * d'exister et d'être testée end-to-end. Implémentation par défaut
 * `NullDeclarationPdfRenderer` (PDF stub minimal). D5 swap le binding
 * vers le vrai renderer (DomPDF + template HTML annexe complet) sans
 * toucher l'Action ni les tests.
 */
interface DeclarationPdfRendererInterface
{
    /**
     * Rend le PDF binary de la déclaration à partir de sa preview
     * complète (calcul brut + clusters + décisions).
     */
    public function render(DeclarationPreviewData $preview): string;
}
