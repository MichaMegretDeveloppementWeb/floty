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
 * « Ajouter à la liste » payload of the « Détails » section: persists a free
 * detail line as a future suggestion. The 100-char cap mirrors
 * `vehicle_event_detail_suggestions.label` / `vehicle_event_details.detail`.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreVehicleEventDetailSuggestionData extends Data
{
    public function __construct(
        #[Required, Max(100)]
        public string $label,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'label.required' => 'Le détail est obligatoire.',
            'label.max' => 'Un détail ne peut pas dépasser 100 caractères.',
        ];
    }
}
