<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Support\VehicleEvent\EventCategoryList;
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
     * `title` / `categories` are nullable WITHOUT a default on purpose (see
     * {@see StoreVehicleEventData}): a defaulted Spatie Data property is
     * validated as `sometimes`, which would skip the `required` rules.
     *
     * @param  list<string>|null  $categories
     */
    public function __construct(
        #[Required]
        public VehicleEventType $type,

        #[Required, Date]
        public string $startDate,

        #[Date, AfterOrEqual('start_date')]
        public ?string $endDate,

        #[Max(500)]
        public ?string $description,

        /** Free name; required (via rules) and kept only for the `other` type. */
        public ?string $title,

        /** User-supplied categories; the Action composes them with the type default. */
        public ?array $categories,

        /** Informative unavailability flag; forced true for known types. */
        public bool $impliesUnavailability = true,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $isOther = ($context->payload['type'] ?? null) === VehicleEventType::Other->value;

        return [
            'title' => ['nullable', 'string', 'max:120', 'required_if:type,other'],
            'implies_unavailability' => ['boolean'],
            'categories' => $isOther
                ? ['required', 'array', 'min:1', 'max:'.EventCategoryList::MAX]
                : ['nullable', 'array', 'max:'.(EventCategoryList::MAX - 1)],
            'categories.*' => ['string', 'max:60', 'distinct:ignore_case'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'type.required' => "Le type d'événement est obligatoire.",
            'title.required_if' => "Le nom de l'événement est obligatoire pour le type « Personnalisé ».",
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'categories.required' => 'Au moins une catégorie est obligatoire.',
            'categories.min' => 'Au moins une catégorie est obligatoire.',
            'categories.max' => 'Vous ne pouvez pas dépasser 5 catégories.',
            'categories.*.distinct' => 'Cette catégorie est déjà présente.',
            'categories.*.max' => 'Une catégorie ne peut pas dépasser 60 caractères.',
        ];
    }
}
