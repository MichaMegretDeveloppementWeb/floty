<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Rules\Vehicle\AvailableForPeriod;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Distinct;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Update payload for an existing contract.
 *
 * Mutates the contract in place (no amendment history in V1; durable trace
 * lives in attached documents). The MySQL anti-overlap trigger excludes
 * the current row via `id <> NEW.id`. Same validation surface as
 * {@see StoreContractData}.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateContractData extends Data
{
    /**
     * @param  list<int>  $driverIds  Distinct driver IDs to attach via the `contract_drivers` pivot (0..N).
     */
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required, IntegerType, Exists('companies', 'id')]
        public int $companyId,

        #[Required, Date]
        public string $startDate,

        #[Required, Date, AfterOrEqual('start_date')]
        public string $endDate,

        #[Max(64)]
        public ?string $contractReference,

        #[Max(5000)]
        public ?string $notes,

        #[Sometimes, Nullable, ArrayType, Distinct]
        public array $driverIds = [],
    ) {}

    /**
     * Dynamic rule: if the vehicle is exited, reject any period that
     * overlaps or extends past `exit_date` (ADR-0018 §5).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $vehicleId = (int) ($payload['vehicle_id'] ?? 0);
        $startDate = (string) ($payload['start_date'] ?? '');
        $endDate = (string) ($payload['end_date'] ?? '');

        $driverIdsRules = [
            'driver_ids' => ['sometimes', 'nullable', 'array'],
            'driver_ids.*' => ['integer', 'exists:drivers,id'],
        ];

        if ($vehicleId === 0 || $startDate === '' || $endDate === '') {
            return $driverIdsRules;
        }

        try {
            $start = CarbonImmutable::parse($startDate);
            $end = CarbonImmutable::parse($endDate);
        } catch (\Exception) {
            return $driverIdsRules;
        }

        return [
            ...$driverIdsRules,
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
            'vehicle_id.required' => 'Le véhicule est obligatoire.',
            'vehicle_id.numeric' => 'Véhicule invalide.',
            'vehicle_id.integer' => 'Véhicule invalide.',
            'vehicle_id.exists' => 'Véhicule introuvable.',
            'company_id.required' => "L'entreprise est obligatoire.",
            'company_id.numeric' => 'Entreprise invalide.',
            'company_id.integer' => 'Entreprise invalide.',
            'company_id.exists' => 'Entreprise introuvable.',
            'start_date.required' => 'La date de début est obligatoire.',
            'start_date.date' => 'La date de début doit être une date valide.',
            'end_date.required' => 'La date de fin est obligatoire.',
            'end_date.date' => 'La date de fin doit être une date valide.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'contract_reference.max' => 'La référence du contrat ne doit pas dépasser :max caractères.',
            'notes.max' => 'Les notes ne doivent pas dépasser :max caractères.',
            'driver_ids.*.integer' => 'Conducteur invalide.',
            'driver_ids.*.exists' => 'Conducteur introuvable.',
            'driver_ids.distinct' => "Un conducteur ne peut être ajouté qu'une seule fois sur la même location.",
        ];
    }
}
