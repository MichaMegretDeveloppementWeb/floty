<?php

declare(strict_types=1);

namespace App\Enums\FiscalDeclaration;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * États du cycle de vie d'une déclaration fiscale pour un couple
 * `(company, year)` (Phase 11 D5.8, audit pré-livraison Proposition IV).
 *
 * Permet au composant frontend `<DeclarationStateCard>` de rendre une
 * carte adaptée à chaque situation, sans masquer la réalité de
 * l'historique :
 *
 *   - **Untouched** (S1) : aucune déclaration préparée pour cette année.
 *     CTA invitation « Préparer la déclaration ».
 *
 *   - **DraftPending** (S2) : Draft en cours avec au moins un cluster
 *     pending. CTA « Reprendre la revue ».
 *
 *   - **DraftReadyToGenerate** (S3) : Draft sans cluster pending,
 *     toutes les décisions sont prises. CTA « Reprendre / Générer ».
 *
 *   - **Deferred** (S4) : déclaration mise de côté volontairement.
 *     CTA « Reprendre ».
 *
 *   - **GeneratedActive** (S5) : déclaration générée, à jour, non
 *     obsolète. CTA « Ouvrir » + « Télécharger PDF ».
 *
 *   - **GeneratedObsoleteOrphan** (S6) : déclaration générée puis
 *     invalidée par une mutation contractuelle (contrat / VFC /
 *     indispo / véhicule modifié), **sans régénération démarrée**.
 *     Affichage avec carte d'alerte ostensible + liste des motifs +
 *     CTA « Régénérer la déclaration ». L'utilisateur doit comprendre
 *     immédiatement qu'une déclaration existe mais qu'elle est
 *     périmée.
 *
 *   - **RegenerationInProgress** (S7) : un Draft chaîné a été créé
 *     pour remplacer une version obsolète, mais n'est pas encore
 *     généré. CTA « Reprendre la régénération » + lien vers la
 *     version obsolète pour référence.
 */
#[TypeScript]
enum DeclarationLifecycleState: string
{
    case Untouched = 'untouched';
    case DraftPending = 'draft_pending';
    case DraftReadyToGenerate = 'draft_ready_to_generate';
    case Deferred = 'deferred';
    case GeneratedActive = 'generated_active';
    case GeneratedObsoleteOrphan = 'generated_obsolete_orphan';
    case RegenerationInProgress = 'regeneration_in_progress';
}
