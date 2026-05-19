<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Update payload for a commercial discount.
 *
 * Intentionally without `companyId`: switching the company of an existing
 * discount is equivalent to a new discount. Project doctrine requires
 * creating a new row rather than mutating attachment (immutable audit of
 * issued invoices). The Edit form renders the company read-only.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateRentalDiscountData extends Data
{
    /**
     * @param  array<int, int>  $vehicleIds
     */
    public function __construct(
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
