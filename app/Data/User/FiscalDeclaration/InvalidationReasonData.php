<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Throwable;

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
     * Hydrate une liste depuis le payload brut du cast Eloquent
     * `fiscal_declarations.obsolete_reasons`. Garde-fou résilient ·
     * retourne `[]` si le payload est mal formé (cast Eloquent qui a
     * renvoyé un scalaire suite à JSON corrompu, ou items qui ne sont
     * pas des `array<string, mixed>` valides). Log warning canal
     * `declarations` pour audit forensic.
     *
     * Centralise la logique précédemment dupliquée dans
     * `DeclarationLifecycleResolver::resolveObsoleteReasons` et
     * `DeclarationController::review` (le second n'avait PAS les guards,
     * d'où un 500 sur la page Review d'une déclaration dont le
     * prédécesseur avait `obsolete_reasons` corrompu · plan-remediation
     * Vague 1, hotfix issu du retour Chrome live D12).
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
