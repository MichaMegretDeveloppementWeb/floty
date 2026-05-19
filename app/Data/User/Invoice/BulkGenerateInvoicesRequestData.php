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
 * Payload du POST `bulk-generate` · génération en masse des annexes en
 * attente pour le couple `(entreprise, année)`. Pas de `month` · l'Action
 * dérive la liste depuis le {@see App\Services\Billing\PendingInvoicesResolver}.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class BulkGenerateInvoicesRequestData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('companies', 'id')]
        public int $companyId,

        #[Required, IntegerType, Between(2020, 2099)]
        public int $year,
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
