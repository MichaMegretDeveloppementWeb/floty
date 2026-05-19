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
 * Transitions a `draft` declaration to `deferred` (ADR-0015 § D4,
 * § 6.2). Voluntary visual marker: the user sets the declaration
 * aside to decide later (typically after consulting the accountant).
 *
 * Refuses any non-`draft` source status. `deferred` continues to
 * forbid generation just like `pending`.
 *
 * Note: direct Eloquent mutation rather than a dedicated write
 * repository method. Tolerated exception (very light mutation, no
 * complex business logic, no cross-table validation, ADR-0013 R3).
 * To revisit if the transition logic grows.
 */
final readonly class MarkDeclarationAsDeferredAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
    ) {}

    public function execute(int $declarationId, ?string $reason = null): FiscalDeclaration
    {
        // Trim and null empty: the textarea may have produced an
        // all-whitespace string. Length validation lives in the
        // FormRequest.
        $normalizedReason = $reason !== null ? trim($reason) : null;
        if ($normalizedReason === '') {
            $normalizedReason = null;
        }

        return DB::transaction(function () use ($declarationId, $normalizedReason): FiscalDeclaration {
            $declaration = $this->reader->findById($declarationId);
            if ($declaration === null) {
                throw new DomainException(sprintf('Déclaration %d introuvable.', $declarationId));
            }

            if ($declaration->status !== FiscalDeclarationStatus::Draft) {
                throw new DomainException(sprintf(
                    'Seule une déclaration en statut « draft » peut être différée (statut courant : %s).',
                    $declaration->status->value,
                ));
            }

            if ($declaration->is_obsolete) {
                throw new DomainException('Une déclaration obsolète ne peut pas être différée ; régénérer une nouvelle déclaration.');
            }

            $declaration->fill([
                'status' => FiscalDeclarationStatus::Deferred,
                'defer_reason' => $normalizedReason,
            ])->save();

            Log::channel('declarations')->notice('FiscalDeclaration.marked_deferred', [
                'declaration_id' => $declaration->id,
                'company_id' => $declaration->company_id,
                'fiscal_year' => $declaration->fiscal_year,
                'actor_user_id' => Auth::id() ?? 0,
                'has_reason' => $normalizedReason !== null,
            ]);

            return $declaration->fresh();
        });
    }
}
