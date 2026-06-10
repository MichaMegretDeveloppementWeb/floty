<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Update vehicle-event payload. No `vehicle_id`: the attached vehicle cannot
 * change. `title` / `categories` / `implies_unavailability` follow the same
 * rules as the create payload (composed / normalised by the Action).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateVehicleEventData extends Data
{
    /**
     * `categories` is nullable WITHOUT a default on purpose (see
     * {@see StoreVehicleEventData}): a defaulted Spatie Data property is
     * validated as `sometimes`, which would skip the `required` rule.
     *
     * @param  list<string>|null  $categories
     */
    public function __construct(
        /** Free name of the event, always required. */
        #[Required]
        public string $title,

        #[Required, Date]
        public string $startDate,

        #[Date, AfterOrEqual('start_date')]
        public ?string $endDate,

        #[Max(500)]
        public ?string $description,

        /** Natures of the event (UI « Nature »), at least one. */
        public ?array $categories,

        /** Unavailability flag; forced true server-side when reductive. */
        public bool $impliesUnavailability = true,

        /** Optional cost (TTC) in cents; costs only, never a revenue. */
        public ?int $amountCents = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'implies_unavailability' => ['boolean'],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string', 'max:60', 'distinct:ignore_case'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'title.required' => "Le nom de l'événement est obligatoire.",
            'title.max' => "Le nom de l'événement ne doit pas dépasser :max caractères.",
            'start_date.required' => 'La date de début est obligatoire.',
            'start_date.date' => 'La date de début doit être une date valide.',
            'end_date.date' => 'La date de fin doit être une date valide.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'description.max' => 'La description ne doit pas dépasser :max caractères.',
            'categories.required' => 'Au moins une nature est obligatoire.',
            'categories.min' => 'Au moins une nature est obligatoire.',
            'categories.*.distinct' => 'Cette nature est déjà présente.',
            'categories.*.max' => 'Une nature ne peut pas dépasser 60 caractères.',
            'amount_cents.numeric' => 'Le montant doit être un nombre.',
            'amount_cents.integer' => 'Le montant doit être un nombre entier.',
            'amount_cents.min' => 'Le montant ne peut pas être négatif.',
        ];
    }
}
