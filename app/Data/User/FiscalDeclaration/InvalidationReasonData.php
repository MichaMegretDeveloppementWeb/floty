<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Throwable;

/**
 * Strict-typed reflection of one entry of the
 * `fiscal_declarations.obsolete_reasons` JSON column (ADR-0015 § 5.1).
 *
 * The touched-entity payload is deliberately a sub-DTO (`type` + `id` +
 * snapshot label) rather than an Eloquent reference: the entity may have
 * been deleted, and the displayed label must reflect its state at the
 * moment of invalidation.
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
     * Hydrate a list from the raw Eloquent payload of
     * `fiscal_declarations.obsolete_reasons`. Returns `[]` when the
     * payload is malformed (scalar from corrupt JSON, items that are not
     * valid `array<string, mixed>`). Logs a warning on the `declarations`
     * channel for forensic audit.
     *
     * @return list<self>
     */
    public static function listFromRaw(mixed $raw, int $declarationId): array
    {
        if ($raw === null) {
            return [];
        }

        if (! is_array($raw)) {
            Log::channel('declarations')->warning('FiscalDeclaration.obsolete_reasons_malformed', [
                'declaration_id' => $declarationId,
                'received_type' => gettype($raw),
            ]);

            return [];
        }

        try {
            return array_map(
                static fn (array $entry): self => self::fromArray($entry),
                $raw,
            );
        } catch (Throwable $e) {
            Log::channel('declarations')->warning('FiscalDeclaration.obsolete_reasons_invalid_entry', [
                'declaration_id' => $declarationId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Hydrate from a raw array as stored in the `obsolete_reasons` JSON
     * column.
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
