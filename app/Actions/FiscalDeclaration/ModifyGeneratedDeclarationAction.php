<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Triggers a voluntary regeneration from a generated, active
 * declaration, without waiting for a scope mutation.
 *
 * Atomic pipeline:
 *   1. Verify the target is Generated + not obsolete; refuse any
 *      other transition.
 *   2. Mark the declaration obsolete with a typed
 *      `VoluntaryModification` reason (ADR-0015 § 5.1, § D9 rev. 1.1).
 *      Sets `obsolete_at` and appends an entry to `obsolete_reasons`
 *      referencing the initiating user.
 *   3. Delegate to {@see RegenerateDeclarationAction} for the new
 *      draft creation and `superseded_by_id` chaining.
 *
 * Reversibility: if the user discards the produced draft without
 * generating it, {@see DiscardDraftDeclarationAction} detects that
 * `obsolete_reasons` only contains `VoluntaryModification` entries
 * and reactivates the previous declaration.
 */
final readonly class ModifyGeneratedDeclarationAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
        private MarkDeclarationAsObsoleteAction $markObsolete,
        private RegenerateDeclarationAction $regenerate,
    ) {}

    public function execute(
        int $generatedDeclarationId,
        int $actorUserId,
        string $actorFullName,
    ): FiscalDeclaration {
        return DB::transaction(function () use ($generatedDeclarationId, $actorUserId, $actorFullName): FiscalDeclaration {
            $current = $this->reader->findById($generatedDeclarationId);

            if ($current === null) {
                throw new DomainException(sprintf('Déclaration %d introuvable.', $generatedDeclarationId));
            }

            if ($current->status !== FiscalDeclarationStatus::Generated || $current->is_obsolete) {
                throw new DomainException(
                    'La modification volontaire est réservée aux déclarations générées et actives.',
                );
            }

            $this->markObsolete->execute($current->id, new InvalidationReasonData(
                type: InvalidationReasonType::VoluntaryModification,
                occurredAt: Carbon::now()->toIso8601String(),
                actorUserId: $actorUserId,
                entity: [
                    'type' => 'user',
                    'id' => $actorUserId,
                    'label' => $actorFullName,
                ],
                fieldsChanged: [],
            ));

            $newDraft = $this->regenerate->execute($current->id);

            Log::channel('declarations')->notice('FiscalDeclaration.voluntary_modification_triggered', [
                'previous_declaration_id' => $current->id,
                'previous_reference' => $current->reference,
                'new_draft_id' => $newDraft->id,
                'actor_user_id' => $actorUserId,
            ]);

            return $newDraft;
        });
    }
}
