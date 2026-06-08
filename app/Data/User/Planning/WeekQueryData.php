<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Validation of `GET /app/planning/week?vehicleId=X&week=N&companyId=Z`.
 *
 * `companyId` is optional: present when the drawer is opened from the
 * company view, enabling company-locked mode (other companies are
 * anonymised and the company picker is locked in the create form).
 */
#[TypeScript]
final class WeekQueryData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required, IntegerType, Between(1, 53)]
        public int $week,

        #[Nullable, IntegerType, Exists('companies', 'id')]
        public ?int $companyId = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicleId.required' => 'Le véhicule est obligatoire.',
            'vehicleId.numeric' => 'Véhicule invalide.',
            'vehicleId.integer' => 'Véhicule invalide.',
            'vehicleId.exists' => 'Ce véhicule est introuvable.',
            'week.required' => 'Le numéro de semaine est obligatoire.',
            'week.numeric' => 'Le numéro de semaine doit être un nombre.',
            'week.integer' => 'Le numéro de semaine doit être un nombre entier.',
            'week.between' => 'Le numéro de semaine doit être compris entre 1 et 53.',
            'companyId.numeric' => 'Entreprise invalide.',
            'companyId.integer' => 'Entreprise invalide.',
            'companyId.exists' => 'Cette entreprise est introuvable.',
        ];
    }
}
