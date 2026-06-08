<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Actions\Contract\BulkCreateContractsAction;
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
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Throwable;

/**
 * Bulk-create payload for contracts from the planning page.
 *
 * Shape: shared `[start_date, end_date]` + type + optional reference, plus
 * the list of `vehicleIds` to assign to `companyId`. A single transaction
 * in {@see BulkCreateContractsAction} inserts N contracts atomically (rolls
 * back if any insert fails, e.g. on the anti-overlap trigger).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class BulkStoreContractsData extends Data
{
    /**
     * @param  list<int>  $vehicleIds
     * @param  list<int>  $driverIds  Drivers attached to every created contract (0..N, via `contract_drivers` pivot).
     */
    public function __construct(
        #[Required, ArrayType, Min(1), Max(100)]
        public array $vehicleIds,

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
     * Extends attribute-based rules:
     *   - `driver_ids.*` must exist in `drivers.id`.
     *   - `vehicle_ids.*` must be integer + exist in `vehicles.id`.
     *   - `vehicle_ids.{idx}` applies {@see AvailableForPeriod} per
     *     vehicle for the shared `[startDate, endDate]` range, enforcing
     *     ADR-0018 §5 on the bulk path as well.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $rules = [
            'driver_ids' => ['sometimes', 'nullable', 'array'],
            'driver_ids.*' => ['integer', 'exists:drivers,id'],
            'vehicle_ids.*' => ['integer', 'exists:vehicles,id'],
        ];

        $payload = $context->payload;
        $vehicleIds = $payload['vehicle_ids'] ?? null;
        $startDate = $payload['start_date'] ?? null;
        $endDate = $payload['end_date'] ?? null;

        // Only attach AvailableForPeriod when the period is parseable.
        // Missing/malformed dates are surfaced by the `Required`/`Date`
        // rules upstream.
        if (
            ! is_array($vehicleIds)
            || ! is_string($startDate) || $startDate === ''
            || ! is_string($endDate) || $endDate === ''
        ) {
            return $rules;
        }

        try {
            $start = CarbonImmutable::parse($startDate);
            $end = CarbonImmutable::parse($endDate);
        } catch (Throwable) {
            return $rules;
        }

        foreach ($vehicleIds as $idx => $vehicleId) {
            $resolvedId = match (true) {
                is_int($vehicleId) => $vehicleId,
                is_string($vehicleId) && ctype_digit($vehicleId) => (int) $vehicleId,
                default => null,
            };

            if ($resolvedId === null) {
                continue;
            }

            $rules["vehicle_ids.{$idx}"] = [
                'integer',
                'exists:vehicles,id',
                new AvailableForPeriod($resolvedId, $start, $end),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicle_ids.required' => 'Sélectionnez au moins un véhicule.',
            'vehicle_ids.array' => 'La liste des véhicules est invalide.',
            'vehicle_ids.min' => 'Sélectionnez au moins un véhicule.',
            'vehicle_ids.max' => 'Création limitée à 100 locations par opération.',
            'vehicle_ids.*.integer' => 'Véhicule invalide.',
            'vehicle_ids.*.exists' => 'Véhicule introuvable.',
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
