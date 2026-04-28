<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Validation des query params de
 * `GET /app/planning/week?vehicleId=X&week=N`.
 */
#[TypeScript]
final class WeekQueryData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required, IntegerType, Between(1, 53)]
        public int $week,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicleId.required' => 'Le véhicule est obligatoire.',
            'vehicleId.exists' => 'Ce véhicule est introuvable.',
            'week.required' => 'Le numéro de semaine est obligatoire.',
            'week.between' => 'Le numéro de semaine doit être compris entre 1 et 53.',
        ];
    }
}
