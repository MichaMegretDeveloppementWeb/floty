<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Actions\Contract\StoreContractAction;
use App\Rules\Vehicle\AvailableForPeriod;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de création d'un contrat (chantier 04.G — page Create).
 *
 * **Validations posées par DB et par les triggers** (en plus de celles
 * ci-dessous) :
 *   - `CHECK end_date >= start_date` (DB)
 *   - Trigger MySQL anti-overlap (refuse si autre contrat actif sur le
 *     même véhicule chevauche la plage)
 *
 * **Validation côté Action** (cf. {@see StoreContractAction}) :
 *   - Pré-vérification d'overlap pour produire un message FR explicite
 *     avant de heurter le trigger DB.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreContractData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required, IntegerType, Exists('companies', 'id')]
        public int $companyId,

        #[IntegerType, Exists('drivers', 'id')]
        public ?int $driverId,

        #[Required, Date]
        public string $startDate,

        #[Required, Date, AfterOrEqual('start_date')]
        public string $endDate,

        #[Max(64)]
        public ?string $contractReference,

        #[Max(5000)]
        public ?string $notes,
    ) {}

    /**
     * Règle dynamique : si le véhicule est sorti de flotte, bloquer
     * tout contrat dont la période chevauche ou dépasse `exit_date`
     * (cf. ADR-0018 § 5).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $vehicleId = (int) ($payload['vehicle_id'] ?? 0);
        $startDate = (string) ($payload['start_date'] ?? '');
        $endDate = (string) ($payload['end_date'] ?? '');

        // Spatie Data : retourner un tableau ici **remplace** les rules
        // de l'attribut `#[Required, Date, AfterOrEqual('start_date')]`
        // sur `end_date`. On doit donc ré-énumérer toutes les rules
        // en ajoutant `AvailableForPeriod` à la liste.
        if ($vehicleId === 0 || $startDate === '' || $endDate === '') {
            return [];
        }

        try {
            $start = CarbonImmutable::parse($startDate);
            $end = CarbonImmutable::parse($endDate);
        } catch (\Exception) {
            return [];
        }

        return [
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                new AvailableForPeriod($vehicleId, $start, $end),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicle_id.exists' => 'Véhicule introuvable.',
            'company_id.exists' => 'Entreprise introuvable.',
            'driver_id.exists' => 'Conducteur introuvable.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
