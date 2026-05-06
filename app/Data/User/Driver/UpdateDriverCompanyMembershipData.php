<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload d'édition d'une membership Driver↔Company existante.
 *
 * Scope V1 (chantier B) : seul `joined_at` est modifiable. La gestion de
 * `left_at` reste pilotée par le workflow Sortir dédié (qui orchestre la
 * résolution des contrats à venir) — voir `LeaveDriverCompanyMembershipData`.
 *
 * La cohérence chronologique avec `left_at` posé sur la membership cible
 * est vérifiée par {@see UpdateDriverCompanyMembershipAction} au moment de
 * l'exécution (pas dans la couche validation, car `left_at` ne fait pas
 * partie du payload).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateDriverCompanyMembershipData extends Data
{
    public function __construct(
        #[Required, Date]
        public string $joinedAt,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'joined_at.required' => "La date d'entrée est obligatoire.",
            'joined_at.date' => "La date d'entrée doit être une date valide.",
        ];
    }
}
