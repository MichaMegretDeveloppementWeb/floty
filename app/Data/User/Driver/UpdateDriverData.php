<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload d'édition d'un conducteur - uniquement firstName/lastName.
 * Les memberships company sont gérées via des endpoints dédiés.
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
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
        ];
    }
}
