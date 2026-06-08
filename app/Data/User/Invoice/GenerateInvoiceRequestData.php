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
            'company_id.required' => "L'entreprise est obligatoire.",
            'company_id.numeric' => 'Entreprise invalide.',
            'company_id.integer' => 'Entreprise invalide.',
            'company_id.exists' => 'Entreprise introuvable.',
            'year.required' => "L'année est obligatoire.",
            'year.numeric' => "L'année doit être un nombre.",
            'year.integer' => "L'année doit être un nombre entier.",
            'year.between' => "L'année doit être comprise entre :min et :max.",
            'month.required' => 'Le mois est obligatoire.',
            'month.numeric' => 'Le mois doit être un nombre.',
            'month.integer' => 'Le mois doit être un nombre entier.',
            'month.between' => 'Le mois doit être compris entre :min et :max.',
        ];
    }
}
