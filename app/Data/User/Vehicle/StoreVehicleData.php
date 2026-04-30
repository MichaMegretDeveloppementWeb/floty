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
 * Payload de création d'un véhicule + ses caractéristiques fiscales
 * initiales (1ʳᵉ ligne de `vehicle_fiscal_characteristics`).
 *
 * Validation conditionnelle : selon `homologationMethod`, l'un des
 * trois champs `co2Wltp` / `co2Nedc` / `taxableHorsepower` devient
 * requis (pattern R-2024-005). Géré dans `rules()` car les attributs
 * Spatie ne supportent pas le `requiredIf` croisé.
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

        // ---------- Spécificités fiscales (toujours visibles) ----------
        #[IntegerType, Min(0), Max(10000)]
        public ?int $kerbMass = null,

        public bool $handicapAccess = false,

        // ---------- Usage spécifique (conditionnels selon catégorie/carrosserie) ----------
        public bool $m1SpecialUse = false,

        public bool $n1PassengerTransport = false,

        public bool $n1RemovableSecondRowSeat = false,

        public bool $n1SkiLiftUse = false,
    ) {}

    /**
     * Règles dynamiques cumulées :
     *  - unique license_plate filtré par soft-delete
     *  - mesures CO₂ / PA conditionnelles à la méthode d'homologation
     *
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
     * Normalisation appliquée AVANT validation : la plaque est toujours
     * stockée en majuscules. Effectuer la transformation ici (et pas
     * dans le repository) garantit que la règle d'unicité
     * (`Rule::unique('vehicles', 'license_plate')`) est testée sur la
     * valeur normalisée — un user ne peut donc pas contourner l'unique
     * en envoyant une casse différente.
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
        ];
    }
}
