<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload d'édition d'une membership Driver↔Company existante.
 *
 * Scope (chantier B + B-bis) : `joined_at` requis + `left_at` optionnel
 * (nullable). Permet 3 cas d'usage avec un seul endpoint :
 *   1. Correction `joined_at` seule (membership active ou sortie).
 *   2. Édition simultanée des deux dates (correction d'une membership
 *      sortie avec date erronée).
 *   3. Réactivation d'une membership sortie : envoyer `left_at: null`.
 *
 * La gestion de `left_at` qui *crée* une sortie reste pilotée par
 * `LeaveDriverCompanyMembershipData` (workflow Q6 avec résolution des
 * contrats à venir). Cette modale-ci ne gère que les corrections post-
 * facto — pas d'orchestration contrats.
 *
 * La cohérence chronologique (`joined_at <= left_at`) est vérifiée par
 * {@see UpdateDriverCompanyMembershipAction} au moment de l'exécution,
 * car la rule conditionnelle « après if not null » n'est pas exprimable
 * comme attribute Spatie.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateDriverCompanyMembershipData extends Data
{
    public function __construct(
        #[Required, Date]
        public string $joinedAt,

        #[Nullable, Date]
        public ?string $leftAt = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'joined_at.required' => "La date d'entrée est obligatoire.",
            'joined_at.date' => "La date d'entrée doit être une date valide.",
            'left_at.date' => 'La date de sortie doit être une date valide.',
        ];
    }
}
