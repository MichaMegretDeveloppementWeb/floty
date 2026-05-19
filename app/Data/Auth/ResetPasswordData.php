<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Password reset form payload.
 *
 * Enforces a >= 8 character password (NIST SP 800-63B minimum) and
 * `Confirmed` requires a matching `password_confirmation` field.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class ResetPasswordData extends Data
{
    public function __construct(
        #[Required]
        public string $token,

        #[Required, Email('rfc'), Max(255)]
        public string $email,

        #[Required, Min(8), Max(255), Confirmed]
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
            'token.required' => 'Le jeton de réinitialisation est manquant.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'Le format de l\'adresse e-mail est invalide.',
            'email.max' => 'L\'adresse e-mail ne peut pas dépasser 255 caractères.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.max' => 'Le mot de passe ne peut pas dépasser 255 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
        ];
    }
}
