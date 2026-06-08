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
use Spatie\LaravelData\Attributes\FromRouteParameter;
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
 * Payload for editing a vehicle from the Edit page.
 *
 * Identity fields update in place. Fiscal fields trigger a new VFC row when
 * any value changes (detected by the Action against the current VFC); in that
 * case `effectiveFrom` + `changeReason` become mandatory. Otherwise the
 * version metadata is ignored.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateVehicleData extends Data
{
    public function __construct(
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

        #[Required, Date, BeforeOrEqual('today')]
        public string $firstFrenchRegistrationDate,

        #[Required, Date, BeforeOrEqual('first_french_registration_date')]
        public string $firstOriginRegistrationDate,

        #[Required, Date, BeforeOrEqual('today')]
        public string $firstEconomicUseDate,

        #[Required, Date, BeforeOrEqual('today')]
        public string $acquisitionDate,

        #[IntegerType, Min(0)]
        public ?int $mileageCurrent,

        #[Max(5000)]
        public ?string $notes,

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

        public bool $acceptsE85 = false,

        #[IntegerType, Min(0), Max(10000)]
        public ?int $kerbMass = null,

        public bool $handicapAccess = false,

        public bool $m1SpecialUse = false,

        public bool $n1PassengerTransport = false,

        public bool $n1RemovableSecondRowSeat = false,

        public bool $n1SkiLiftUse = false,

        // When true, a fiscal change creates a new VFC version (legacy flow,
        // requires `effectiveFrom` + `changeReason`). When false (default), a
        // fiscal change is applied in-place on the current VFC, retroactive
        // over the whole effective period.
        public bool $createNewVersion = false,

        #[Date]
        public ?string $effectiveFrom = null,

        public ?FiscalCharacteristicsChangeReason $changeReason = null,

        #[Max(2000)]
        public ?string $changeNote = null,

        // Vehicle id is injected from the `{vehicle}` route parameter so that
        // `Rule::unique` can exclude it without coupling to `request()`
        // (ADR-0013).
        #[FromRouteParameter('vehicle')]
        public ?int $_vehicleId = null,
    ) {}

    /**
     * The "fiscal field changed?" check requires the current VFC and is not
     * expressible here; {@see UpdateVehicleAction} guards it at runtime and
     * raises MissingNewVersionMetadataException when needed.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $method = $payload['homologation_method'] ?? null;
        $energy = $payload['energy_source'] ?? null;
        $reason = $payload['change_reason'] ?? null;
        $vehicleId = (int) ($payload['_vehicle_id'] ?? 0);

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
                'nullable',
                Rule::in($allowedReasons),
            ],
            'change_note' => [
                Rule::requiredIf(fn (): bool => $isOther),
            ],
        ];
    }

    /**
     * Normalise the license plate to uppercase before validation.
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
            'license_plate.required' => "La plaque d'immatriculation est obligatoire.",
            'license_plate.max' => "La plaque d'immatriculation ne doit pas dépasser :max caractères.",
            'license_plate.unique' => 'Une autre immatriculation active est déjà enregistrée.',
            'brand.required' => 'La marque est obligatoire.',
            'brand.max' => 'La marque ne doit pas dépasser :max caractères.',
            'model.required' => 'Le modèle est obligatoire.',
            'model.max' => 'Le modèle ne doit pas dépasser :max caractères.',
            'vin.max' => 'Le VIN ne doit pas dépasser :max caractères.',
            'color.max' => 'La couleur ne doit pas dépasser :max caractères.',
            'first_french_registration_date.required' => 'La date de 1ère immatriculation française est obligatoire.',
            'first_french_registration_date.date' => 'La date de 1ère immatriculation française doit être une date valide.',
            'first_french_registration_date.before_or_equal' => 'La date de 1ère immatriculation française ne peut pas être dans le futur.',
            'first_origin_registration_date.required' => "La date de 1ère immatriculation d'origine est obligatoire.",
            'first_origin_registration_date.date' => "La date de 1ère immatriculation d'origine doit être une date valide.",
            'first_origin_registration_date.before_or_equal' => "La date d'origine doit être antérieure ou égale à la date française.",
            'first_economic_use_date.required' => 'La date de 1ère affectation économique est obligatoire.',
            'first_economic_use_date.date' => 'La date de 1ère affectation économique doit être une date valide.',
            'first_economic_use_date.before_or_equal' => 'La date de 1ère affectation économique ne peut pas être dans le futur.',
            'acquisition_date.required' => "La date d'acquisition est obligatoire.",
            'acquisition_date.date' => "La date d'acquisition doit être une date valide.",
            'acquisition_date.before_or_equal' => "La date d'acquisition ne peut pas être dans le futur.",
            'mileage_current.numeric' => 'Le kilométrage doit être un nombre.',
            'mileage_current.integer' => 'Le kilométrage doit être un nombre entier.',
            'mileage_current.min' => 'Le kilométrage ne peut pas être négatif.',
            'notes.max' => 'Les notes ne doivent pas dépasser :max caractères.',
            'reception_category.required' => 'La catégorie de réception est obligatoire.',
            'reception_category.enum' => 'La catégorie de réception sélectionnée est invalide.',
            'vehicle_user_type.required' => 'Le genre du véhicule est obligatoire.',
            'vehicle_user_type.enum' => 'Le genre du véhicule sélectionné est invalide.',
            'body_type.required' => 'La carrosserie est obligatoire.',
            'body_type.enum' => 'La carrosserie sélectionnée est invalide.',
            'seats_count.required' => 'Le nombre de places est obligatoire.',
            'seats_count.numeric' => 'Le nombre de places doit être un nombre.',
            'seats_count.integer' => 'Le nombre de places doit être un nombre entier.',
            'seats_count.min' => "Le nombre de places doit être d'au moins :min.",
            'seats_count.max' => 'Le nombre de places ne peut pas dépasser :max.',
            'energy_source.required' => "La source d'énergie est obligatoire.",
            'energy_source.enum' => "La source d'énergie sélectionnée est invalide.",
            'underlying_combustion_engine_type.required' => 'Le type de moteur thermique sous-jacent est obligatoire pour les véhicules hybrides.',
            'underlying_combustion_engine_type.enum' => 'Le type de moteur thermique sous-jacent sélectionné est invalide.',
            'euro_standard.enum' => 'La norme Euro sélectionnée est invalide.',
            'homologation_method.required' => "La méthode d'homologation est obligatoire.",
            'homologation_method.enum' => "La méthode d'homologation sélectionnée est invalide.",
            'co2_wltp.required' => 'Le CO₂ WLTP est obligatoire quand la méthode d\'homologation est WLTP.',
            'co2_wltp.numeric' => 'Le CO₂ WLTP doit être un nombre.',
            'co2_wltp.integer' => 'Le CO₂ WLTP doit être un nombre entier.',
            'co2_wltp.min' => 'Le CO₂ WLTP ne peut pas être négatif.',
            'co2_wltp.max' => 'Le CO₂ WLTP ne peut pas dépasser :max.',
            'co2_nedc.required' => 'Le CO₂ NEDC est obligatoire quand la méthode d\'homologation est NEDC.',
            'co2_nedc.numeric' => 'Le CO₂ NEDC doit être un nombre.',
            'co2_nedc.integer' => 'Le CO₂ NEDC doit être un nombre entier.',
            'co2_nedc.min' => 'Le CO₂ NEDC ne peut pas être négatif.',
            'co2_nedc.max' => 'Le CO₂ NEDC ne peut pas dépasser :max.',
            'taxable_horsepower.required' => 'La puissance administrative est obligatoire quand la méthode d\'homologation est PA.',
            'taxable_horsepower.numeric' => 'La puissance administrative doit être un nombre.',
            'taxable_horsepower.integer' => 'La puissance administrative doit être un nombre entier.',
            'taxable_horsepower.min' => "La puissance administrative doit être d'au moins :min.",
            'taxable_horsepower.max' => 'La puissance administrative ne peut pas dépasser :max.',
            'kerb_mass.numeric' => 'La masse à vide doit être un nombre.',
            'kerb_mass.integer' => 'La masse à vide doit être un nombre entier.',
            'kerb_mass.min' => 'La masse à vide ne peut pas être négative.',
            'kerb_mass.max' => 'La masse à vide ne peut pas dépasser :max.',
            'effective_from.date' => "La date d'effet doit être une date valide.",
            'change_reason.in' => 'Motif invalide pour une nouvelle version.',
            'change_note.required' => 'La note est obligatoire pour le motif « Autre changement ».',
            'change_note.max' => 'La note ne doit pas dépasser :max caractères.',
        ];
    }
}
