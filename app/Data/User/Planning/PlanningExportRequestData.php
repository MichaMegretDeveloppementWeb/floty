<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use App\Enums\Planning\PlanningExportMode;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Planning PDF export request payload.
 *
 * The vehicle ids come from the client's currently filtered heatmap
 * list. Amounts and usage days are NEVER trusted from the client · the
 * export recomputes them server-side from the fiscal engine. The
 * `companyId` is set only from the per-company planning view (scopes the
 * weekly numbers and the real tax to that company).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class PlanningExportRequestData extends Data
{
    /**
     * @param  array<int, int>  $vehicleIds
     */
    public function __construct(
        public array $vehicleIds,
        public int $year,
        public PlanningExportMode $mode,
        public ?int $companyId = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'vehicle_ids' => ['required', 'array', 'min:1'],
            'vehicle_ids.*' => ['integer'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'mode' => ['required', new Enum(PlanningExportMode::class)],
            'company_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicle_ids.required' => 'Sélectionnez au moins un véhicule à exporter.',
            'vehicle_ids.array' => 'La sélection de véhicules est invalide.',
            'vehicle_ids.min' => 'Sélectionnez au moins un véhicule à exporter.',
            'vehicle_ids.*.integer' => 'La sélection de véhicules est invalide.',
            'year.required' => "L'année de l'export est obligatoire.",
            'year.integer' => "L'année de l'export est invalide.",
            'year.min' => "L'année de l'export est invalide.",
            'year.max' => "L'année de l'export est invalide.",
            'mode.required' => "Le type d'export est obligatoire.",
            'mode.enum' => "Le type d'export sélectionné est invalide.",
            'company_id.integer' => "L'entreprise sélectionnée est invalide.",
        ];
    }
}
