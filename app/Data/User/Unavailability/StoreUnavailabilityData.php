<?php

declare(strict_types=1);

namespace App\Data\User\Unavailability;

use App\Enums\Unavailability\UnavailabilityType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de création d'une indisponibilité.
 *
 * `has_fiscal_impact` n'est PAS dans le payload — il est calculé
 * côté Action depuis l'enum (`UnavailabilityType::hasFiscalImpact()`).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreUnavailabilityData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required]
        public UnavailabilityType $type,

        #[Required, Date]
        public string $startDate,

        #[Date, AfterOrEqual('start_date')]
        public ?string $endDate,

        #[Max(500)]
        public ?string $description,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicle_id.required' => 'Le véhicule est obligatoire.',
            'vehicle_id.exists' => 'Ce véhicule est introuvable.',
            'type.required' => "Le type d'indisponibilité est obligatoire.",
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
