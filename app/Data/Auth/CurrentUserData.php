<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation minimale de l'utilisateur connecté, exposée aux pages
 * Inertia via les shared props `auth.user`.
 *
 * `null` quand aucun utilisateur n'est authentifié - exprimé côté
 * shared props via `?CurrentUserData`.
 */
#[TypeScript]
final class CurrentUserData extends Data
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public string $email,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->getKey(),
            firstName: $user->first_name,
            lastName: $user->last_name,
            fullName: $user->full_name,
            email: $user->email,
        );
    }
}
