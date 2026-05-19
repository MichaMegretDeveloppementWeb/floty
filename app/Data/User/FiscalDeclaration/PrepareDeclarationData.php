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
}
