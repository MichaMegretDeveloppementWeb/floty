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
 * Authenticated user "change my password" form payload.
 *
 * `currentPassword` is verified by {@see ChangePasswordAction} via
 * Hash::check rather than at the validation layer to avoid exposing the
 * hash mechanism. `Different` forbids reusing the same password.
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
