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
     * D5.5, calculée par {@see App\Services\Fiscal\Declaration\DeclarationReferenceGenerator}).
     */
    public function markAsGenerated(
        int $declarationId,
        string $pdfPath,
        string $pdfHash,
        string $reference,
    ): void;

    /**
     * Chaîne une déclaration obsolète vers sa version régénérée
     * (`superseded_by_id = $newId`). Appelé par `RegenerateDeclarationAction`
     * (D3) après création de la nouvelle ligne.
     */
    public function linkSupersededBy(int $oldId, int $newId): void;
}
