<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * « Ajouter à la liste » payload: persists a free nature typed on the event
 * form as a future (non-reductive) catalogue suggestion. The 60-char cap
 * mirrors `vehicle_event_natures.label` / `vehicle_event_categories.category`.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreVehicleEventNatureSuggestionData extends Data
{
    public function __construct(
        #[Required, Max(60)]
        public string $label,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'label.required' => 'La nature est obligatoire.',
            'label.max' => 'Une nature ne peut pas dépasser 60 caractères.',
        ];
    }
}
