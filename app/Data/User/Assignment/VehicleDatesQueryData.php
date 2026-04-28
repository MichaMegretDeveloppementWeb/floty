<?php

declare(strict_types=1);

namespace App\Data\User\Assignment;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Validation des query params de
 * `GET /app/assignments/vehicle-dates?vehicleId=X&year=YYYY`.
 *
 * Spatie Data — injecté en signature du controller, validation
 * automatique au moment de la résolution.
 */
#[TypeScript]
final class VehicleDatesQueryData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(1), Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required, IntegerType, Min(2000), Max(2100)]
        public int $year,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicleId.required' => 'Le véhicule est obligatoire.',
            'vehicleId.exists' => 'Ce véhicule est introuvable.',
            'year.required' => "L'année est obligatoire.",
            'year.between' => "L'année doit être comprise entre 2000 et 2100.",
        ];
    }
}
