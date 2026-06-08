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
 * Payload of `POST /app/planning/preview-rentals`. Mirrors
 * {@see PreviewTaxesInputData} field-for-field so the frontend can
 * compose them.
 *
 * The underlying service builds a synthetic, non-persisted contract over
 * the min/max of the provided dates and computes a rent consistent with
 * the final monthly invoice (`OptimalRateBreakdown` per month +
 * `DiscountApplier` for active discounts).
 */
#[TypeScript]
final class PreviewRentalsInputData extends Data
{
    /**
     * @param  list<string>  $dates  ISO (YYYY-MM-DD); the service uses min/max to rebuild the range.
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
