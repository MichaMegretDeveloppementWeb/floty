<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Models\FiscalDeclaration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marks a declaration as obsolete and pushes a typed reason onto
 * `obsolete_reasons` (ADR-0015 § D8/D9).
 *
 * Idempotent: callable repeatedly to stack successive reasons.
 * `obsolete_at` is only set on the first call; later reasons append
 * to the JSON array.
 *
 * Per the immutability doctrine (ADR-0015 § D4 rev. 1.1), the status
 * stays as-is (`generated`, `draft` or `deferred`); only the
 * `is_obsolete` flag flips. The historical PDF stays on disk.
 *
 * Called by `DeclarationInvalidationDetector`, which resolves which
 * declarations are impacted by a Contract / VFC / VehicleEvent
 * mutation.
 */
final readonly class MarkDeclarationAsObsoleteAction
{
    public function __construct(
        private FiscalDeclarationWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $declarationId, InvalidationReasonData $reason): FiscalDeclaration
    {
        // Returns the mutated entity so the signature stays aligned
        // with the other declaration Actions; lets the caller chain
        // without an extra read.
        $declaration = DB::transaction(
            fn (): FiscalDeclaration => $this->writer->markAsObsolete($declarationId, $reason),
        );

        Log::channel('declarations')->notice('FiscalDeclaration.marked_obsolete', [
            'declaration_id' => $declarationId,
            'reason_type' => $reason->type->value,
            'occurred_at' => $reason->occurredAt,
            'actor_user_id' => $reason->actorUserId,
            'entity_type' => $reason->entity['type'],
            'entity_id' => $reason->entity['id'],
            'fields_changed' => $reason->fieldsChanged,
        ]);

        return $declaration;
    }
}
