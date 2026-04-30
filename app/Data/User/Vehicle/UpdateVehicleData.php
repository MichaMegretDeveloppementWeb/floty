<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\Vehicle\VehicleUserType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload d'édition d'un véhicule depuis la page Edit.
 *
 * Edit ne sert qu'aux **changements réels** du véhicule dans le temps
 * (conversion E85, retrofit, transformation N1, …) qui justifient
 * l'INSERT d'une nouvelle VFC. Les corrections de saisie sur une VFC
 * existante passent exclusivement par la modale Historique
 * (cf. {@see UpdateFiscalCharacteristicsData}).
 *
 * Le DTO porte :
 *   - les champs **identité** (table `vehicles`, toujours updatables
 *     en place sans historisation),
 *   - les champs **fiscaux** de la nouvelle VFC à créer,
 *   - les **bornes + motif/note** posés sur la nouvelle VFC.
 *
 * `effectiveFrom` + `changeReason` sont toujours requis. `changeNote`
 * est requise uniquement si `changeReason = OtherChange`.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateVehicleData extends Data
{
    public function __construct(
        // ---------- Identité ----------
        #[Required, Max(20)]
        public string $licensePlate,

        #[Required, Max(80)]
        public string $brand,

        #[Required, Max(120)]
        public string $model,

        #[Max(20)]
        public ?string $vin,

        #[Max(30)]
        public ?string $color,

        #[Required, Date]
        public string $firstFrenchRegistrationDate,

        #[Required, Date, BeforeOrEqual('first_french_registration_date')]
        public string $firstOriginRegistrationDate,

        #[Required, Date]
        public string $firstEconomicUseDate,

        #[Required, Date]
        public string $acquisitionDate,

        #[IntegerType, Min(0)]
        public ?int $mileageCurrent,

        #[Max(5000)]
        public ?string $notes,

        // ---------- Fiscal ----------
        #[Required]
        public ReceptionCategory $receptionCategory,

        #[Required]
        public VehicleUserType $vehicleUserType,

        #[Required]
        public BodyType $bodyType,

        #[Required, IntegerType, Min(1), Max(20)]
        public int $seatsCount,

        #[Required]
        public EnergySource $energySource,

        public ?UnderlyingCombustionEngineType $underlyingCombustionEngineType,

        public ?EuroStandard $euroStandard,

        #[Required]
        public HomologationMethod $homologationMethod,

        #[IntegerType, Min(0), Max(999)]
        public ?int $co2Wltp,

        #[IntegerType, Min(0), Max(999)]
        public ?int $co2Nedc,

        #[IntegerType, Min(1), Max(99)]
        public ?int $taxableHorsepower,

        // ---------- Métadonnées de changement (toujours requises) ----------
        #[Required, Date]
        public string $effectiveFrom,

        #[Required]
        public FiscalCharacteristicsChangeReason $changeReason,

        #[Max(2000)]
        public ?string $changeNote = null,
    ) {}

    /**
     * Règles dynamiques :
     *  - `license_plate` unique sauf pour le véhicule lui-même.
     *  - Mesure CO₂ / PA conditionnelle à la méthode d'homologation.
     *  - `change_reason` doit être un motif user-sélectionnable
     *    (`InitialCreation` reste réservé au système — c'est le
     *    Repository qui le pose à la création du véhicule).
     *  - Si `change_reason = other_change` : `change_note` requise.
     *  - Si véhicule hybride : `underlying_combustion_engine_type` requis.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $method = $payload['homologation_method'] ?? null;
        $energy = $payload['energy_source'] ?? null;
        $reason = $payload['change_reason'] ?? null;
        // Récupère l'id du véhicule depuis le route param `{vehicle}`
        // pour exclure le véhicule en cours de la règle d'unicité
        // license_plate (sinon l'utilisateur ne pourrait pas
        // soumettre sans modifier la plaque).
        $vehicleId = (int) (request()->route('vehicle') ?? 0);

        $isOther = $reason === FiscalCharacteristicsChangeReason::OtherChange->value;
        $isHybrid = in_array($energy, [
            EnergySource::PluginHybrid->value,
            EnergySource::NonPluginHybrid->value,
            EnergySource::ElectricHydrogen->value,
        ], true);

        $allowedReasons = array_map(
            static fn (FiscalCharacteristicsChangeReason $r): string => $r->value,
            FiscalCharacteristicsChangeReason::userSelectableForNewVersion(),
        );

        return [
            'license_plate' => [
                Rule::unique('vehicles', 'license_plate')
                    ->ignore($vehicleId)
                    ->whereNull('deleted_at'),
            ],
            'co2_wltp' => [
                Rule::requiredIf(fn (): bool => $method === HomologationMethod::Wltp->value),
            ],
            'co2_nedc' => [
                Rule::requiredIf(fn (): bool => $method === HomologationMethod::Nedc->value),
            ],
            'taxable_horsepower' => [
                Rule::requiredIf(fn (): bool => $method === HomologationMethod::Pa->value),
            ],
            'underlying_combustion_engine_type' => [
                Rule::requiredIf(fn (): bool => $isHybrid),
            ],
            'change_reason' => [
                Rule::in($allowedReasons),
            ],
            'change_note' => [
                Rule::requiredIf(fn (): bool => $isOther),
            ],
        ];
    }

    /**
     * Normalisation pré-validation : license_plate en majuscules
     * (cohérent avec `StoreVehicleData::prepareForPipeline`).
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (isset($properties['license_plate']) && is_string($properties['license_plate'])) {
            $properties['license_plate'] = mb_strtoupper($properties['license_plate']);
        }

        return $properties;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'license_plate.unique' => 'Une autre immatriculation active est déjà enregistrée.',
            'first_origin_registration_date.before_or_equal' => "La date d'origine doit être antérieure ou égale à la date française.",
            'co2_wltp.required' => 'Le CO₂ WLTP est obligatoire quand la méthode d\'homologation est WLTP.',
            'co2_nedc.required' => 'Le CO₂ NEDC est obligatoire quand la méthode d\'homologation est NEDC.',
            'taxable_horsepower.required' => 'La puissance administrative est obligatoire quand la méthode d\'homologation est PA.',
            'underlying_combustion_engine_type.required' => 'Le type de moteur thermique sous-jacent est obligatoire pour les véhicules hybrides.',
            'effective_from.required' => 'La date d\'effet est obligatoire pour une nouvelle version.',
            'change_reason.required' => 'Le motif est obligatoire pour une nouvelle version.',
            'change_reason.in' => 'Motif invalide pour une nouvelle version.',
            'change_note.required' => 'La note est obligatoire pour le motif « Autre changement ».',
        ];
    }
}
