<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de l'endpoint `POST /app/planning/preview-taxes`.
 *
 * Validation Spatie Data - utilisée via `Data::from($request)` ou
 * `Data::validateAndCreate()` selon le besoin. Marqueur `#[TypeScript]`
 * pour exposer le type côté front (typage de `useApi.post<TInput, …>`).
 */
#[TypeScript]
final class PreviewTaxesInputData extends Data
{
    /**
     * @param  list<string>  $dates  Dates ISO (YYYY-MM-DD)
     */
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required, IntegerType, Exists('companies', 'id')]
        public int $companyId,

        #[Required, ArrayType, Min(1)]
        public array $dates,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'dates.*' => ['required', new DateFormat('Y-m-d')],
        ];
    }
}
