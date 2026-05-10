<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Support\Carbon;

final class FiscalDeclarationWriteRepository implements FiscalDeclarationWriteRepositoryInterface
{
    public function persist(array $attributes): FiscalDeclaration
    {
        $declaration = new FiscalDeclaration;
        $declaration->fill($attributes);
        $declaration->save();

        return $declaration;
    }

    public function markAsObsolete(int $declarationId, InvalidationReasonData $reason): void
    {
        $declaration = FiscalDeclaration::query()->findOrFail($declarationId);

        $reasons = is_array($declaration->obsolete_reasons)
            ? $declaration->obsolete_reasons
            : [];

        $reasons[] = [
            'type' => $reason->type->value,
            'occurred_at' => $reason->occurredAt,
            'actor_user_id' => $reason->actorUserId,
            'entity' => $reason->entity,
            'fields_changed' => $reason->fieldsChanged,
        ];

        $attributes = ['obsolete_reasons' => $reasons];
        if (! $declaration->is_obsolete) {
            $attributes['is_obsolete'] = true;
            $attributes['obsolete_at'] = Carbon::now();
        }

        $declaration->fill($attributes);
        $declaration->save();
    }

    public function markAsGenerated(
        int $declarationId,
        string $pdfPath,
        string $pdfHash,
        string $reference,
        array $snapshotPayload,
    ): void {
        // Lock pessimiste + double-check atomique du statut/obsolescence
        // pour fermer la fenêtre TOCTOU entre la décision de générer
        // (`GenerateDeclarationAction::guardCanGenerate`) et la
        // finalisation BDD. Si une mutation concurrente a fait passer
        // la déclaration en `obsolete` ou changé son statut entre
        // temps, l'INSERT échoue proprement.
        $declaration = FiscalDeclaration::query()
            ->whereKey($declarationId)
            ->where('status', FiscalDeclarationStatus::Draft->value)
            ->where('is_obsolete', false)
            ->lockForUpdate()
            ->first();

        if ($declaration === null) {
            throw new DomainException(sprintf(
                'Génération impossible : la déclaration %d n\'est plus en statut « draft » non obsolète (mutation concurrente).',
                $declarationId,
            ));
        }

        $declaration->fill([
            'status' => FiscalDeclarationStatus::Generated,
            'generated_at' => Carbon::now(),
            'generated_pdf_path' => $pdfPath,
            'generated_pdf_hash' => $pdfHash,
            'reference' => $reference,
            'generated_snapshot_payload' => $snapshotPayload,
        ]);
        $declaration->save();
    }

    public function linkSupersededBy(int $oldId, int $newId): void
    {
        FiscalDeclaration::query()
            ->whereKey($oldId)
            ->update(['superseded_by_id' => $newId]);
    }
}
