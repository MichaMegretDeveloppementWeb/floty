<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalDeclaration;

use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Models\FiscalDeclaration;

/**
 * Écritures FiscalDeclaration (Phase 11 D1, ADR-0015 § 5.1 rev. 1.1).
 *
 * Les déclarations émises sont immuables : pas de méthode `update`
 * généraliste. Les seules mutations autorisées sont :
 *   - `markAsObsolete` : flag d'obsolescence + append d'un motif typé
 *   - `markAsGenerated` : matérialisation du PDF lié à une déclaration
 *     `draft`/`deferred`
 *   - `linkSupersededBy` : chaînage `obsolete -> régénéré`
 *
 * La logique métier (transition d'état, append du motif) vit dans les
 * Actions D3 ; les méthodes ici sont des primitives SQL atomiques.
 */
interface FiscalDeclarationWriteRepositoryInterface
{
    /**
     * Persiste une déclaration en base. Utilisé par les Actions D3
     * (création initiale + régénération).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function persist(array $attributes): FiscalDeclaration;

    /**
     * Marque la déclaration `$declarationId` comme obsolète et append le
     * motif `$reason` au tableau JSON `obsolete_reasons`. Idempotent :
     * si déjà obsolète, le motif est tout de même ajouté à l'historique
     * (un seul `obsolete_at` toutefois, qui correspond au premier flag).
     */
    public function markAsObsolete(int $declarationId, InvalidationReasonData $reason): void;

    /**
     * Matérialise une déclaration `draft`/`deferred` en `generated` :
     * passage du statut + pose des champs PDF + persistance de la
     * référence lisible `DECL-{shortCode}-{year}-{NNNN}` (Phase 11
     * D5.5, calculée par {@see App\Services\Fiscal\Declaration\DeclarationReferenceGenerator})
     * + persistance du snapshot fiscal complet (Phase 11 D5.7.5,
     * audit pré-livraison B5).
     *
     * **Lock pessimiste + double-check atomique** sur l'invariant
     * `status=draft && is_obsolete=false` : ferme la fenêtre TOCTOU
     * entre le guard de l'Action et la finalisation BDD. Throws
     * `DomainException` si l'invariant n'est plus tenu (mutation
     * concurrente).
     *
     * @param  array<string, mixed>  $snapshotPayload  Sérialisation JSON du DTO {@see App\Data\User\FiscalDeclaration\FiscalDeclarationSnapshotData}
     */
    public function markAsGenerated(
        int $declarationId,
        string $pdfPath,
        string $pdfHash,
        string $reference,
        array $snapshotPayload,
    ): void;

    /**
     * Chaîne une déclaration obsolète vers sa version régénérée
     * (`superseded_by_id = $newId`). Appelé par `RegenerateDeclarationAction`
     * (D3) après création de la nouvelle ligne.
     */
    public function linkSupersededBy(int $oldId, int $newId): void;

    /**
     * Soft delete une déclaration. Pose `deleted_at` sans purger les
     * données (auditabilité préservée, ADR-0015 doctrine immuabilité).
     * Utilisé par `DiscardDraftDeclarationAction` (Phase 13 D5.10.E)
     * pour annuler un brouillon créé par erreur ou abandonné.
     */
    public function softDelete(int $declarationId): void;

    /**
     * Ré-active une déclaration obsolète : remet `is_obsolete = false`,
     * vide `obsolete_at`, `obsolete_reasons` et `superseded_by_id`.
     * Utilisé par `DiscardDraftDeclarationAction` (Phase 13 D5.10.E)
     * quand la suppression d'un brouillon qui était une régénération
     * volontaire doit rendre son predecessor à nouveau actif. **Ne
     * réactive PAS** si l'obsolescence avait des motifs réels
     * (mutation périmètre) · la décision revient à l'Action.
     */
    public function reactivate(int $declarationId): void;

    /**
     * Délie une déclaration de son successeur (`superseded_by_id = NULL`)
     * sans toucher au flag `is_obsolete` ni aux motifs. Utilisé par
     * `DiscardDraftDeclarationAction` (Phase 13 D5.10.E) quand la
     * suppression d'un brouillon de régénération doit laisser le
     * predecessor obsolète mais permettre de relancer une nouvelle
     * régénération.
     */
    public function unlinkSupersededBy(int $declarationId): void;
}
