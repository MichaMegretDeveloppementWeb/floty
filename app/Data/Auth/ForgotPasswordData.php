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
 * "Forgot password" form payload. The 255-char bound is symmetric with
 * LoginRequest to prevent soft-DoS via huge input on the rate-limiter
 * key or the `password_reset_tokens` table.
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
