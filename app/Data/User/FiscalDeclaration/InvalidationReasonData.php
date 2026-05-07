<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Reflet d'une entrée du JSON `fiscal_declarations.obsolete_reasons`
 * (Phase 11 D1, ADR-0015 § 5.1 + D9 rev. 1.1). Schéma typé strict :
 * pas de texte libre, chaque entrée décrit une cause atomique
 * d'obsolescence.
 *
 * Le tableau d'entités touchées est délibérément un sub-DTO simplifié
 * (`type` + `id` + label snapshoté) plutôt qu'une référence Eloquent :
 * l'entité peut avoir été supprimée, et le label affiché doit refléter
 * son état au moment de l'invalidation, pas son état actuel.
 */
#[TypeScript]
final class InvalidationReasonData extends Data
{
    /**
     * @param  array{type: string, id: int, label: string}  $entity
     * @param  list<string>  $fieldsChanged
     */
    public function __construct(
        public InvalidationReasonType $type,
        /** ISO 8601 (Y-m-d\TH:i:sP). */
        public string $occurredAt,
        public int $actorUserId,
        public array $entity,
        public array $fieldsChanged,
    ) {}

    /**
     * Hydrate depuis un tableau brut tel que stocké dans la colonne
     * JSON `obsolete_reasons`.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        /** @var array{type: string, id: int, label: string} $entity */
        $entity = $raw['entity'];

        /** @var list<string> $fieldsChanged */
        $fieldsChanged = $raw['fields_changed'] ?? [];

        return new self(
            type: InvalidationReasonType::from((string) $raw['type']),
            occurredAt: (string) $raw['occurred_at'],
            actorUserId: (int) $raw['actor_user_id'],
            entity: $entity,
            fieldsChanged: $fieldsChanged,
        );
    }
}
