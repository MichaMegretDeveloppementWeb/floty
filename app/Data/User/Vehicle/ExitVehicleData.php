<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\VehicleExitReason;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de sortie de flotte d'un véhicule (depuis la modale Sortie).
 *
 * Cf. ADR-0018 § 8.1 - modale Sortie. Le `vehicleId` n'est pas dans le
 * payload, il est lu depuis le paramètre de route `{vehicle}`.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class ExitVehicleData extends Data
{
    public function __construct(
        #[Required, Date, BeforeOrEqual('today')]
        public string $exitDate,

        #[Required]
        public VehicleExitReason $exitReason,

        #[Max(2000)]
        public ?string $note = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'exit_date.required' => 'La date de sortie est obligatoire.',
            'exit_date.date' => 'La date de sortie doit être une date valide.',
            'exit_date.before_or_equal' => 'La date de sortie ne peut pas être dans le futur.',
            'exit_reason.required' => 'Le motif de sortie est obligatoire.',
            'note.max' => 'La note ne peut pas dépasser 2000 caractères.',
        ];
    }
}
