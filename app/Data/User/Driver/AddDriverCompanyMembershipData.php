<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload d'ajout d'une nouvelle membership Driver↔Company.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class AddDriverCompanyMembershipData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('companies', 'id')]
        public int $companyId,

        #[Required, Date]
        public string $joinedAt,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'company_id.required' => 'L\'entreprise est obligatoire.',
            'company_id.exists' => 'Entreprise introuvable.',
            'joined_at.required' => 'La date d\'entrée est obligatoire.',
        ];
    }
}
