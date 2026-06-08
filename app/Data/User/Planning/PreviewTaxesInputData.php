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
 * Payload of `POST /app/planning/preview-taxes`. Validation by Spatie
 * Data via `Data::from($request)` or `Data::validateAndCreate()`.
 */
#[TypeScript]
final class PreviewTaxesInputData extends Data
{
    /**
     * @param  list<string>  $dates  ISO (YYYY-MM-DD).
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

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicleId.required' => 'Le véhicule est obligatoire.',
            'vehicleId.numeric' => 'Véhicule invalide.',
            'vehicleId.integer' => 'Véhicule invalide.',
            'vehicleId.exists' => 'Véhicule introuvable.',
            'companyId.required' => "L'entreprise est obligatoire.",
            'companyId.numeric' => 'Entreprise invalide.',
            'companyId.integer' => 'Entreprise invalide.',
            'companyId.exists' => 'Entreprise introuvable.',
            'dates.required' => 'Au moins une date est obligatoire.',
            'dates.min' => 'Au moins une date est obligatoire.',
            'dates.*.required' => 'Chaque date est obligatoire.',
            'dates.*.date_format' => 'Chaque date doit être au format attendu.',
        ];
    }
}
