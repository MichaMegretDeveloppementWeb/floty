<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Create payload for a commercial discount.
 *
 * `discountBasisPoints` is in basis points (1..10000 = 0.01%..100%). The
 * UI captures a percentage with `0.5..100` step `0.5` and submits
 * `bp = round(percent * 100)`.
 *
 * `vehicleIds` may be empty (applicative semantics: "applies to every
 * vehicle of the company over the period", decoded by `DiscountResolver`).
 *
 * `notes` is a free long-text field, never surfaced outside the Show page.
 *
 * Overlap validation lives in
 * {@see App\Services\RentalDiscount\RentalDiscountConflictService} (not
 * expressible as a CHECK constraint nor a single Laravel rule).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreRentalDiscountData extends Data
{
    /**
     * @param  array<int, int>  $vehicleIds
     */
    public function __construct(
        #[Required, IntegerType, Exists('companies', 'id')]
        public int $companyId,

        #[Required, Date]
        public string $startDate,

        #[Required, Date, AfterOrEqual('start_date')]
        public string $endDate,

        #[Required, IntegerType, Between(1, 10000)]
        public int $discountBasisPoints,

        #[Nullable, Max(120)]
        public ?string $label = null,

        #[Nullable, Max(5000)]
        public ?string $notes = null,

        /**
         * Empty or null list means "applies to every vehicle of the
         * company". Nullable type so Spatie does not generate a `required`
         * rule on the field (see {@see static::rules()}).
         *
         * @var array<int, int>|null
         */
        public ?array $vehicleIds = [],
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'company_id.required' => 'Une entreprise est obligatoire.',
            'company_id.exists' => 'Entreprise introuvable.',
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.required' => 'La date de fin est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'discount_basis_points.required' => 'Le pourcentage est obligatoire.',
            'discount_basis_points.between' => 'Le pourcentage doit être compris entre 0,01 % et 100 %.',
            'label.max' => 'Le libellé est limité à 120 caractères.',
            'notes.max' => 'Les notes internes sont limitées à 5 000 caractères.',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'vehicle_ids' => ['nullable', 'array'],
            'vehicle_ids.*' => ['integer', 'exists:vehicles,id'],
        ];
    }
}
