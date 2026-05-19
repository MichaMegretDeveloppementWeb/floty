<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalDeclaration;

use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;

/**
 * FiscalDeclaration writes (ADR-0015 § 5.1 rev. 1.1).
 *
 * Emitted declarations are immutable: no general-purpose `update`
 * method. The only allowed mutations are:
 *   - `markAsObsolete` · obsolescence flag + append of a typed reason
 *   - `markAsGenerated` · materialisation of the PDF attached to a
 *     `draft`/`deferred` declaration
 *   - `linkSupersededBy` · chaining `obsolete -> regenerated`
 *
 * The business logic (state transitions, reason append) lives in the
 * Actions; methods here are atomic SQL primitives.
 */
interface FiscalDeclarationWriteRepositoryInterface
{
    /**
     * Persists a declaration. Used by Actions (initial creation +
     * regeneration).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function persist(array $attributes): FiscalDeclaration;

    /**
     * Marks declaration `$declarationId` as obsolete and appends
     * `$reason` to the JSON array `obsolete_reasons`. Idempotent: if
     * already obsolete, the reason is still appended to the history
     * (only one `obsolete_at` is kept, corresponding to the first
     * flag).
     *
     * Returns the freshly updated model so callers can use the mutated
     * entity without re-reading.
     */
    public function markAsObsolete(int $declarationId, InvalidationReasonData $reason): FiscalDeclaration;

    /**
     * Materialises a `draft`/`deferred` declaration as `generated`:
     * transitions the status, sets the PDF fields, persists the
     * readable reference `DECL-{shortCode}-{year}-{NNNN}` (computed by
     * {@see App\Services\Fiscal\Declaration\DeclarationReferenceGenerator})
     * + persists the full fiscal snapshot.
     *
     * Pessimistic lock + atomic double-check on the invariant
     * `status=draft && is_obsolete=false`: closes the TOCTOU window
     * between the Action guard and the DB finalisation. Throws
     * `DomainException` if the invariant no longer holds (concurrent
     * mutation).
     *
     * @param  array<string, mixed>  $snapshotPayload  JSON payload of {@see App\Data\User\FiscalDeclaration\FiscalDeclarationSnapshotData}
     */
    public function markAsGenerated(
        int $declarationId,
        string $pdfPath,
        string $pdfHash,
        string $reference,
        array $snapshotPayload,
    ): void;

    /**
     * Chains an obsolete declaration to its regenerated version
     * (`superseded_by_id = $newId`). Called by
     * `RegenerateDeclarationAction` after creating the new row.
     */
    public function linkSupersededBy(int $oldId, int $newId): void;

    /**
     * Soft-deletes a declaration. Sets `deleted_at` without purging the
     * data (auditability preserved, ADR-0015 immutability doctrine).
     * Used by `DiscardDraftDeclarationAction` to cancel a draft created
     * by mistake or abandoned.
     */
    public function softDelete(int $declarationId): void;

    /**
     * Soft-delete with pessimistic lock + atomic double-check on the
     * invariant `status ∈ $allowedStatuses` (closes the TOCTOU window
     * between `findById` in the Action and `delete` in the repo).
     * Pattern aligned with {@see markAsGenerated()}.
     *
     * Throws `DomainException` if the declaration no longer exists or
     * if its status is no longer in the allowed list (concurrent
     * mutation, e.g. another user already deleted or generated it).
     *
     * Returns the locked model (refreshed from DB), so callers can
     * read `company_id`, `fiscal_year`, `superseded_by_id`, `status`
     * etc. in the same transaction without re-reading.
     *
     * @param  list<FiscalDeclarationStatus>  $allowedStatuses
     */
    public function softDeleteWithLock(int $declarationId, array $allowedStatuses): FiscalDeclaration;

    /**
     * Pessimistic lock on the predecessor of a draft being deleted.
     * Must be called within the same transaction as
     * {@see softDeleteWithLock()} to guarantee that no other operation
     * mutates the predecessor between the detection of the
     * `superseded_by_id` link and its potential reactivation/unlinking.
     * Returns `null` if the predecessor no longer exists (extreme race
     * condition).
     */
    public function lockPredecessor(int $predecessorId): ?FiscalDeclaration;

    /**
     * Re-activates an obsolete declaration: resets `is_obsolete = false`,
     * clears `obsolete_at` and `superseded_by_id`. Used by
     * `DiscardDraftDeclarationAction` when deleting a draft that was a
     * voluntary regeneration should make its predecessor active again.
     * Does NOT reactivate if the obsolescence had real reasons (scope
     * mutation) · the decision belongs to the Action.
     *
     * `obsolete_reasons` is PRESERVED, not purged · the JSON array
     * keeps the audit trace of the obsolescence history ("this
     * declaration went through a voluntary modification attempt that
     * was cancelled on DATE"). Only the active state flag is reset.
     * The optional purge of `fiscal_review_decisions` (tactical user
     * choices of the replaced cycle) is the responsibility of the
     * calling Action, not the repo.
     */
    public function reactivate(int $declarationId): void;

    /**
     * Unlinks a declaration from its successor (`superseded_by_id =
     * NULL`) without touching the `is_obsolete` flag or the reasons.
     * Used by `DiscardDraftDeclarationAction` when deleting a
     * regeneration draft should leave the predecessor obsolete but
     * allow a new regeneration to be started.
     */
    public function unlinkSupersededBy(int $declarationId): void;
}
