<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventType;
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
 * Update vehicle-event payload. No `vehicle_id`: the attached vehicle
 * cannot change. `title` / `category` / `implies_unavailability` follow the
 * same rules as the create payload (normalised by the Action).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateVehicleEventData extends Data
{
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

        /** Free category; required (via rules) and kept only for the `other` type. */
        public ?string $category,

        /** Informative unavailability flag; forced true for known types. */
        public bool $impliesUnavailability = true,
    ) {}

    /**
     * Custom identity is required only for the `other` type.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120', 'required_if:type,other'],
            'category' => ['nullable', 'string', 'max:60', 'required_if:type,other'],
            'implies_unavailability' => ['boolean'],
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
            'category.required_if' => 'La catégorie est obligatoire pour le type « Personnalisé ».',
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
