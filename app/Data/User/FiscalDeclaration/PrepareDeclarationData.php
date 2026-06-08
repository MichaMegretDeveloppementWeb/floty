<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Actions\FiscalDeclaration\CreateDraftDeclarationAction;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Payload for the "Préparer la déclaration" button on the company page.
 * Creates a draft record through
 * {@see CreateDraftDeclarationAction}.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class PrepareDeclarationData extends Data
{
    public function __construct(
        public int $companyId,
        #[Min(2020), Max(2099)]
        public int $fiscalYear,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'fiscal_year' => ['required', 'integer', 'between:2020,2099'],
        ];
    }

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
            'fiscal_year.required' => "L'année fiscale est obligatoire.",
            'fiscal_year.numeric' => "L'année fiscale doit être un nombre.",
            'fiscal_year.integer' => "L'année fiscale doit être un nombre entier.",
            'fiscal_year.between' => "L'année fiscale doit être comprise entre :min et :max.",
            'fiscal_year.min' => "L'année fiscale ne peut pas être antérieure à :min.",
            'fiscal_year.max' => "L'année fiscale ne peut pas être postérieure à :max.",
        ];
    }
}
