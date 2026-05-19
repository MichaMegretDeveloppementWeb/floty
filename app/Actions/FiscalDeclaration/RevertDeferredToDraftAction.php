<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reverts a deferred declaration to `draft`. Reciprocal of
 * {@see MarkDeclarationAsDeferredAction}.
 *
 * Semantic contract: the user resumes editing a draft they had set
 * aside. Review decisions, any `superseded_by_id`, and
 * `obsolete_*` data are fully preserved; only the status changes.
 *
 * Refuses any non-`deferred` source status. Same tolerated direct
 * Eloquent mutation as {@see MarkDeclarationAsDeferredAction}
 * (light mutation, ADR-0013 R3).
 */
final readonly class RevertDeferredToDraftAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
    ) {}

    public function execute(int $declarationId): FiscalDeclaration
    {
        return DB::transaction(function () use ($declarationId): FiscalDeclaration {
            $declaration = $this->reader->findById($declarationId);
            if ($declaration === null) {
                throw new DomainException(sprintf('Déclaration %d introuvable.', $declarationId));
            }

            if ($declaration->status !== FiscalDeclarationStatus::Deferred) {
                throw new DomainException(sprintf(
                    'Seule une déclaration en statut « deferred » peut voir sa mise en attente annulée (statut courant · %s).',
                    $declaration->status->value,
                ));
            }

            // Clear `defer_reason` on revert: transient state kept
            // coherent with the status. A later deferral will record
            // a fresh reason.
            $declaration->fill([
                'status' => FiscalDeclarationStatus::Draft,
                'defer_reason' => null,
            ])->save();

            Log::channel('declarations')->notice('FiscalDeclaration.reverted_to_draft', [
                'declaration_id' => $declaration->id,
                'company_id' => $declaration->company_id,
                'fiscal_year' => $declaration->fiscal_year,
                'actor_user_id' => Auth::id() ?? 0,
            ]);

            return $declaration->fresh();
        });
    }
}
