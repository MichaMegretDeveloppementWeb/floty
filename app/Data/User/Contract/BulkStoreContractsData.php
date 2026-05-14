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
 * Payload de création multiple de contrats - création groupée depuis
 * la page planning.
 *
 * **Forme** : une plage commune `[start_date, end_date]` + un type
 * + une référence optionnelle, et la liste des `vehicleIds` à attribuer
 * à la `companyId`. Une transaction unique en {@see BulkCreateContractsAction}
 * crée N contrats en bloc, avec rollback complet si l'un des inserts
 * échoue (notamment via le trigger anti-overlap MySQL).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class BulkStoreContractsData extends Data
{
    /**
     * @param  list<int>  $vehicleIds
     * @param  list<int>  $driverIds  Conducteurs à associer à chacun des contrats créés (0, 1 ou plusieurs ; pivot `contract_drivers`).
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

        // Liste de conducteurs partagée appliquée à chaque contrat créé
        // (cf. chantier #3 multi-conducteurs). Default `[]` cohérent avec
        // {@see StoreContractData} - `Sometimes` évite l'auto-required.
        #[Sometimes, Nullable, ArrayType, Distinct]
        public array $driverIds = [],
    ) {}

    /**
     * Étend les règles attributs-based ·
     *
     *   - `driver_ids.*` · chaque ID doit exister dans `drivers.id`.
     *   - `vehicle_ids.*` · chaque ID doit être un integer et exister
     *     dans `vehicles.id` (sécurise le pipeline bulk contre les IDs
     *     orphelines · cf. F-12-002).
     *   - `vehicle_ids.{idx}` · pour chaque véhicule du tableau, applique
     *     {@see AvailableForPeriod} avec la plage `[startDate, endDate]`
     *     commune. Empêche la création d'un contrat dont la période
     *     dépasse `vehicles.exit_date` (ADR-0018 § 5). Comble le trou
     *     historique où la voie bulk court-circuitait cette invariante.
     *
     * Cf. plan-remédiation Vague 1 Lot 1 D8 (F-12-002).
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

        // AvailableForPeriod par véhicule · attache la rule uniquement
        // si on a une période parseable. Si elle est manquante ou
        // malformée, les règles `Required` / `Date` sur startDate/endDate
        // traitent l'erreur en amont · pas besoin de doubler ici.
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
            return $rules; // dates malformées · règles `Date` lèveront en amont
        }

        foreach ($vehicleIds as $idx => $vehicleId) {
            $resolvedId = match (true) {
                is_int($vehicleId) => $vehicleId,
                is_string($vehicleId) && ctype_digit($vehicleId) => (int) $vehicleId,
                default => null,
            };

            if ($resolvedId === null) {
                continue; // règle `integer` sur vehicle_ids.* échouera en amont
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
            'vehicle_ids.array' => 'La liste des véhicules est invalide.',
            'vehicle_ids.min' => 'Sélectionnez au moins un véhicule.',
            'vehicle_ids.max' => 'Création limitée à 100 locations par opération.',
            'vehicle_ids.*.exists' => 'Véhicule introuvable.',
            'company_id.exists' => 'Entreprise introuvable.',
            'driver_ids.*.exists' => 'Conducteur introuvable.',
            'driver_ids.distinct' => "Un conducteur ne peut être ajouté qu'une seule fois sur la même location.",
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
