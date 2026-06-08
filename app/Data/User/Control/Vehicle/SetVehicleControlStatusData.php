<?php

declare(strict_types=1);

namespace App\Data\User\Control\Vehicle;

use App\Enums\Control\VehicleControlStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Quick status toggle (active / paused / disabled) for a control on a vehicle
 * (Chantier B / B2). Targets a global definition or a vehicle-specific override
 * (exactly one).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class SetVehicleControlStatusData extends Data
{
    #[Required]
    public VehicleControlStatus $status;

    public ?int $controlDefinitionId = null;

    public ?int $vehicleControlOverrideId = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'control_definition_id' => ['nullable', 'integer', 'exists:control_definitions,id', 'required_without:vehicle_control_override_id'],
            'vehicle_control_override_id' => ['nullable', 'integer', 'exists:vehicle_control_overrides,id', 'required_without:control_definition_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'status.required' => 'Le statut est obligatoire.',
            'status.enum' => 'Le statut sélectionné est invalide.',
            'control_definition_id.numeric' => 'Contrôle ciblé invalide.',
            'control_definition_id.integer' => 'Contrôle ciblé invalide.',
            'control_definition_id.exists' => 'Contrôle ciblé introuvable.',
            'control_definition_id.required_without' => 'Le contrôle ciblé est obligatoire.',
            'vehicle_control_override_id.numeric' => 'Contrôle ciblé invalide.',
            'vehicle_control_override_id.integer' => 'Contrôle ciblé invalide.',
            'vehicle_control_override_id.exists' => 'Contrôle ciblé introuvable.',
            'vehicle_control_override_id.required_without' => 'Le contrôle ciblé est obligatoire.',
        ];
    }
}
