<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
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
 * Payload for creating a vehicle and its initial VFC row.
 *
 * Cross-field validation in `rules()`: depending on `homologationMethod`,
 * one of `co2Wltp` / `co2Nedc` / `taxableHorsepower` becomes required
 * (R-2024-005); for hybrids `underlyingCombustionEngineType` is required.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreVehicleData extends Data
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

        // E85 flag · L. 421-125 superfuel rebate (BOFiP BOI-AIS-MOB-10-20-40-20250604 § 160).
        public bool $acceptsE85 = false,

        #[IntegerType, Min(0), Max(10000)]
        public ?int $kerbMass = null,

        public bool $handicapAccess = false,

        public bool $m1SpecialUse = false,

        public bool $n1PassengerTransport = false,

        public bool $n1RemovableSecondRowSeat = false,

        public bool $n1SkiLiftUse = false,

        // Optional purchase price (cost, TTC) in cents · recorded on the
        // "Entrée en flotte" lifecycle event. Costs only.
        #[IntegerType, Min(0)]
        public ?int $acquisitionAmountCents = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $method = $payload['homologation_method'] ?? null;
        $energy = $payload['energy_source'] ?? null;

        $isHybrid = in_array($energy, [
            EnergySource::PluginHybrid->value,
            EnergySource::NonPluginHybrid->value,
            EnergySource::ElectricHydrogen->value,
        ], true);

        return [
            'license_plate' => [
                Rule::unique('vehicles', 'license_plate')->whereNull('deleted_at'),
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
        ];
    }

    /**
     * Normalise the license plate to uppercase before unique-validation runs.
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
            'acquisition_amount_cents.numeric' => "Le prix d'acquisition doit être un nombre.",
            'acquisition_amount_cents.integer' => "Le prix d'acquisition doit être un nombre entier.",
            'acquisition_amount_cents.min' => "Le prix d'acquisition ne peut pas être négatif.",
        ];
    }
}
