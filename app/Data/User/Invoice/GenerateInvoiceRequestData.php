<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload for POST generate invoice (Spatie Data validation, no inline
 * `$request->validate([...])`).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class GenerateInvoiceRequestData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('companies', 'id')]
        public int $companyId,

        #[Required, IntegerType, Between(2020, 2099)]
        public int $year,

        #[Required, IntegerType, Between(1, 12)]
        public int $month,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'company_id.exists' => 'Entreprise introuvable.',
        ];
    }
}
