<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload for editing a driver's identity. Company memberships are managed
 * through dedicated endpoints.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateDriverData extends Data
{
    public function __construct(
        #[Required, Max(100)]
        public string $firstName,

        #[Required, Max(100)]
        public string $lastName,

        #[Nullable, Email, Max(255)]
        public ?string $email = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'first_name.max' => 'Le prénom ne doit pas dépasser :max caractères.',
            'last_name.required' => 'Le nom est obligatoire.',
            'last_name.max' => 'Le nom ne doit pas dépasser :max caractères.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.max' => "L'adresse email ne doit pas dépasser :max caractères.",
        ];
    }
}
