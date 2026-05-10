<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marque une déclaration comme obsolète et empile un motif typé dans
 * `obsolete_reasons` (Phase 11 D3, ADR-0015 § D8/D9).
 *
 * Idempotent : appelable plusieurs fois pour empiler des motifs
 * successifs (multi-invalidations). Le `obsolete_at` n'est posé qu'au
 * premier appel ; les motifs ultérieurs s'ajoutent au tableau JSON.
 *
 * Conformément à la doctrine immuabilité (ADR-0015 § D4 rev. 1.1) : le
 * statut reste `generated` (ou `draft`/`deferred` si l'invalidation
 * intervient avant génération), seul le flag `is_obsolete` change. Le
 * PDF historique reste intact sur disque.
 *
 * Appelée par `DeclarationInvalidationDetector` (D3.D) qui résout les
 * déclarations impactées par une mutation Contract / VFC / Unavailability.
 */
final readonly class MarkDeclarationAsObsoleteAction
{
    public function __construct(
        private FiscalDeclarationWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $declarationId, InvalidationReasonData $reason): void
    {
        DB::transaction(fn () => $this->writer->markAsObsolete($declarationId, $reason));

        Log::info('FiscalDeclaration marked obsolete', [
            'declaration_id' => $declarationId,
            'reason_type' => $reason->type->value,
            'occurred_at' => $reason->occurredAt,
            'actor_user_id' => $reason->actorUserId,
            'entity_type' => $reason->entity['type'],
            'entity_id' => $reason->entity['id'],
            'fields_changed' => $reason->fieldsChanged,
        ]);
    }
}
