<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Actions\Driver\CreateDriverAction;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload for creating a driver. The initial company membership is created
 * by {@see CreateDriverAction}.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreDriverData extends Data
{
    public function __construct(
        #[Required, Max(100)]
        public string $firstName,

        #[Required, Max(100)]
        public string $lastName,

        #[Required, IntegerType, Exists('companies', 'id')]
        public int $initialCompanyId,

        #[Required, Date]
        public string $initialJoinedAt,

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
            'last_name.required' => 'Le nom est obligatoire.',
            'initial_company_id.required' => 'Une entreprise initiale est obligatoire.',
            'initial_company_id.exists' => 'Entreprise introuvable.',
            'initial_joined_at.required' => 'La date d\'entrée dans l\'entreprise est obligatoire.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
        ];
    }
}
