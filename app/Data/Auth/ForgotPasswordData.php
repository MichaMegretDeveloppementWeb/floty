<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * DTO entrée du formulaire « Mot de passe oublié ».
 *
 * Borne `max:255` symétrique au LoginRequest pour éviter le soft-DoS via
 * input gigantesque sur la clé RateLimiter ou la table
 * `password_reset_tokens` (cf. plan-remédiation Vague 1 Lot 2 D4 +
 * F-10-009).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class ForgotPasswordData extends Data
{
    public function __construct(
        #[Required, Email('rfc'), Max(255)]
        public string $email,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'Le format de l\'adresse e-mail est invalide.',
            'email.max' => 'L\'adresse e-mail ne peut pas dépasser 255 caractères.',
        ];
    }
}
