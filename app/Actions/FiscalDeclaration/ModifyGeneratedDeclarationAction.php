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
 * Déclenche une régénération volontaire depuis une déclaration générée
 * et active (S5 → S7) sans attendre une mutation de périmètre (Phase 13
 * D5.10.E).
 *
 * Pipeline atomique :
 *   1. Vérifie que la déclaration cible est bien S5 (status=Generated
 *      ET is_obsolete=false). Refuse toute autre transition.
 *   2. Marque la déclaration obsolète avec le motif typé
 *      `VoluntaryModification` (ADR-0015 § 5.1 + D9 rev. 1.1). Le
 *      `obsolete_at` est posé, le `obsolete_reasons` JSON empile une
 *      entrée référençant l'utilisateur initiateur.
 *   3. Délègue à `RegenerateDeclarationAction` la création du nouveau
 *      brouillon et le chaînage `superseded_by_id`. Pas de duplication
 *      de logique.
 *
 * Réutilisable côté UI via le bouton « Modifier la déclaration »
 * exposé sur `<PdfCard>` page Show pour les S5 active.
 *
 * **Réversibilité** · si l'utilisateur supprime le brouillon créé sans
 * passer par génération, `DiscardDraftDeclarationAction` détecte que
 * `obsolete_reasons` ne contient que des entrées VoluntaryModification
 * et ré-active automatiquement la déclaration précédente (S5 retrouvé).
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

            Log::info('FiscalDeclaration voluntary modification triggered', [
                'previous_declaration_id' => $current->id,
                'previous_reference' => $current->reference,
                'new_draft_id' => $newDraft->id,
                'actor_user_id' => $actorUserId,
            ]);

            return $newDraft;
        });
    }
}
