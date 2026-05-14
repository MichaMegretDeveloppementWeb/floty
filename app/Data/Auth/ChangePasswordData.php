<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Actions\Auth\ChangePasswordAction;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Different;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * DTO du formulaire « Changer mon mot de passe » (utilisateur connecté).
 *
 * `currentPassword` est vérifié par {@see ChangePasswordAction}
 * via Hash::check (pas dans la couche validation pour ne pas exposer la
 * mécanique de hash). `Different` empêche de réutiliser le même password
 * (UX · prévient une soumission accidentelle sans changement réel).
 *
 * Cf. plan-remédiation Vague 1 Lot 2 D4.4 (F-10-006) + ADR-0012 rev. 1.1.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class ChangePasswordData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $currentPassword,

        #[Required, Min(8), Max(255), Confirmed, Different('current_password')]
        public string $password,

        #[Required]
        public string $passwordConfirmation,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'current_password.max' => 'Le mot de passe actuel ne peut pas dépasser 255 caractères.',
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.max' => 'Le nouveau mot de passe ne peut pas dépasser 255 caractères.',
            'password.confirmed' => 'La confirmation du nouveau mot de passe ne correspond pas.',
            'password.different' => 'Le nouveau mot de passe doit être différent du mot de passe actuel.',
            'password_confirmation.required' => 'La confirmation du nouveau mot de passe est obligatoire.',
        ];
    }
}
