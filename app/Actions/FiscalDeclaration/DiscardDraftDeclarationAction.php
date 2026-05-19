<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionWriteRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes a draft (or deferred) declaration and reconciles the
 * predecessor's `superseded_by_id` chain.
 *
 * Atomic pipeline with pessimistic locks (close the TOCTOU window
 * for concurrent access):
 *   1. Lock + soft-delete the target with a double status check
 *      (Draft or Deferred). Refuses any deletion of a Generated row
 *      (immutability, ADR-0008).
 *   2. Search for a predecessor and lock it pessimistically for the
 *      same transaction.
 *   3. If a predecessor exists:
 *      - All its `obsolete_reasons` are `VoluntaryModification` (see
 *        {@see ModifyGeneratedDeclarationAction}): fully reactivate
 *        the predecessor (the active S5 is recovered). The
 *        `obsolete_reasons` array is preserved as audit trail; only
 *        the flag, the date and the chain pointer are cleaned.
 *      - Otherwise (real perimeter-change reasons present): unlink
 *        `superseded_by_id` only so the predecessor stays obsolete
 *        and can be regenerated again later.
 *
 * The `fiscal_review_decisions` of the `(company, fiscal_year)`
 * couple are purged at the start of the transaction: the decisions
 * of the replaced cycle have no meaning on a future new draft. A
 * coexisting generated declaration is safe (its decisions are frozen
 * in its `generated_snapshot_payload`).
 *
 * Soft-delete via `Model::delete()`; the trashed record remains
 * queryable through `withTrashed()` for forensic audit but vacates
 * the active slot of the `(company, year)` couple so the next
 * `CreateDraftDeclarationAction::findActiveForCompanyYear` returns
 * null.
 */
final readonly class DiscardDraftDeclarationAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
        private FiscalDeclarationWriteRepositoryInterface $writer,
        private FiscalReviewDecisionWriteRepositoryInterface $decisionsWriter,
    ) {}

    public function execute(int $draftDeclarationId): FiscalDeclarationStatus
    {
        return DB::transaction(function () use ($draftDeclarationId): FiscalDeclarationStatus {
            // Lock + atomic status double-check. Throws DomainException
            // if the declaration is gone or no longer deletable
            // (concurrent mutation).
            $draft = $this->writer->softDeleteWithLock($draftDeclarationId, [
                FiscalDeclarationStatus::Draft,
                FiscalDeclarationStatus::Deferred,
            ]);

            $originalStatus = $draft->status;

            // Pessimistic lock on the predecessor to prevent a
            // concurrent mutation between read and reactivate-vs-unlink
            // decision. The predecessor is the declaration whose
            // `superseded_by_id` points to this draft.
            $predecessor = null;
            $found = $this->reader->findPredecessorOf($draft->id);
            if ($found !== null) {
                $predecessor = $this->writer->lockPredecessor($found->id);
            }

            // Wipe the review decisions for `(company, fiscal_year)`.
            // Otherwise `DeclarationPreviewService` would reload them
            // on the next draft (its uniqueness key is
            // `(company, year, fingerprint)`, not declaration_id).
            // Safe even if a generated declaration coexists: its
            // decisions are frozen in its snapshot payload.
            $deletedDecisions = $this->decisionsWriter->deleteByCompanyYear(
                $draft->company_id,
                $draft->fiscal_year,
            );

            $predecessorReactivated = false;
            if ($predecessor !== null) {
                $reasons = is_array($predecessor->obsolete_reasons) ? $predecessor->obsolete_reasons : [];
                $isVoluntaryOnly = $reasons !== []
                    && array_reduce(
                        $reasons,
                        static fn (bool $carry, array $r): bool => $carry
                            && ($r['type'] ?? null) === InvalidationReasonType::VoluntaryModification->value,
                        true,
                    );

                if ($isVoluntaryOnly) {
                    $this->writer->reactivate($predecessor->id);
                    $predecessorReactivated = true;
                } else {
                    $this->writer->unlinkSupersededBy($predecessor->id);
                }
            }

            Log::channel('declarations')->notice('FiscalDeclaration.draft_discarded', [
                'draft_id' => $draft->id,
                'company_id' => $draft->company_id,
                'fiscal_year' => $draft->fiscal_year,
                'original_status' => $originalStatus->value,
                'predecessor_id' => $predecessor?->id,
                'predecessor_reactivated' => $predecessorReactivated,
                'review_decisions_deleted' => $deletedDecisions,
                'actor_user_id' => Auth::id() ?? 0,
            ]);

            return $originalStatus;
        });
    }
}
