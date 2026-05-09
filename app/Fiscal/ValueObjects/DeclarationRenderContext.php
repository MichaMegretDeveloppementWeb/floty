<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Data\User\FiscalDeclaration\DeclarationPreviewData;
use Carbon\CarbonImmutable;

/**
 * Contexte complet de rendu d'une déclaration fiscale (Phase 11 D5.4).
 *
 * Embarque les trois sources d'information nécessaires à la production
 * du PDF annexe documentaire :
 *
 *   - {@see $preview} (D3) : clusters re-détectés enrichis des décisions
 *     persistées (source d'information sur les chaînes LCD). Utilisé par
 *     le template pour la section « Décisions de revue appliquées ».
 *   - {@see $snapshot} (D5.2) : montants fiscaux post-décisions
 *     Requalified (source de vérité pour le PDF). Utilisé par le
 *     template pour la synthèse fiscale et le détail par véhicule.
 *   - {@see $reference} (D5.3) : numéro lisible
 *     `DECL-{shortCode}-{year}-{NNNN}`. Affichée en encadré dans
 *     l'en-tête et dans le sceau de génération.
 *   - {@see $generatedAt} : horodatage de génération, affiché dans le
 *     sceau et le pied de page.
 *
 * Construit ad-hoc par {@see App\Actions\FiscalDeclaration\GenerateDeclarationAction}
 * en D5.5 ; consommé par {@see App\Services\Pdf\BladeDomPdfDeclarationRenderer}.
 */
final readonly class DeclarationRenderContext
{
    public function __construct(
        public DeclarationPreviewData $preview,
        public FiscalDeclarationSnapshot $snapshot,
        public string $reference,
        public CarbonImmutable $generatedAt,
    ) {}
}
