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
 * Payload d'édition d'un véhicule depuis la page Edit.
 *
 * Le DTO porte :
 *   - les champs **identité** (table `vehicles`, toujours updatables
 *     en place sans historisation),
 *   - les champs **fiscaux** courants (utilisés pour détecter une
 *     modification fiscale et, le cas échéant, créer une nouvelle VFC),
 *   - les **métadonnées** d'une éventuelle nouvelle version
 *     (`effectiveFrom`, `changeReason`, `changeNote`) - toutes optionnelles.
 *
 * Logique d'application (cf. {@see UpdateVehicleAction}) :
 *   - L'identité est toujours mise à jour en place.
 *   - Si au moins un champ fiscal a changé par rapport à la VFC
 *     courante : une nouvelle ligne d'historique est créée. Dans ce
 *     cas `effectiveFrom` et `changeReason` deviennent indispensables
 *     (et la validation runtime côté Action lève
 *     {@see App\Exceptions\Vehicle\MissingNewVersionMetadataException}
 *     si elles manquent).
 *   - Si aucun champ fiscal n'a changé : les métadonnées sont
 *     ignorées, aucune nouvelle VFC n'est créée.
 *
 * Les corrections de saisie sur une VFC existante passent exclusivement
 * par la modale Historique (cf. {@see UpdateFiscalCharacteristicsData}).
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

        // ---------- Spécificités fiscales (toujours visibles) ----------
        #[IntegerType, Min(0), Max(10000)]
        public ?int $kerbMass = null,

        public bool $handicapAccess = false,

        // ---------- Usage spécifique (conditionnels selon catégorie/carrosserie) ----------
        public bool $m1SpecialUse = false,

        public bool $n1PassengerTransport = false,

        public bool $n1RemovableSecondRowSeat = false,

        public bool $n1SkiLiftUse = false,

        // ---------- Métadonnées de la nouvelle version (optionnelles) ----------
        // Requises uniquement si un champ fiscal a changé - détecté par
        // l'Action qui compare le payload à la VFC courante. Si aucun
        // changement fiscal, ces champs sont ignorés.
        #[Date]
        public ?string $effectiveFrom = null,

        public ?FiscalCharacteristicsChangeReason $changeReason = null,

        #[Max(2000)]
        public ?string $changeNote = null,

        // ---------- Identifiant interne (route binding) ----------
        // Identifiant du véhicule injecté automatiquement depuis le
        // paramètre de route `{vehicle}` quand le DTO est construit
        // depuis une Request HTTP. Permet aux règles de validation
        // (notamment `Rule::unique` sur `license_plate`) de s'exclure
        // elles-mêmes sans dépendre du global `request()`.
        // Préfixé `_` pour signaler "interne, jamais saisi par
        // l'utilisateur, jamais utilisé par le frontend".
        // Conforme ADR-0013 : substitue l'appel à `request()->route()`
        // qui couplait directement le DTO à la couche HTTP.
        #[FromRouteParameter('vehicle')]
        public ?int $_vehicleId = null,
    ) {}

    /**
     * Règles dynamiques :
     *  - `license_plate` unique sauf pour le véhicule lui-même.
     *  - Mesure CO₂ / PA conditionnelle à la méthode d'homologation.
     *  - Si véhicule hybride : `underlying_combustion_engine_type` requis.
     *  - `change_reason` (si fourni) doit être un motif user-sélectionnable.
     *  - `change_note` requise uniquement si `change_reason = other_change`.
     *
     * Note : `effective_from` et `change_reason` ne sont pas
     * `requiredIf` ici parce que la condition (« un champ fiscal a-t-il
     * changé ? ») dépend de la VFC courante, inaccessible au DTO. Le
     * filet de sécurité est posé dans {@see UpdateVehicleAction} qui
     * lève {@see MissingNewVersionMetadataException} si fiscal modifié
     * sans métadonnées.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $method = $payload['homologation_method'] ?? null;
        $energy = $payload['energy_source'] ?? null;
        $reason = $payload['change_reason'] ?? null;
        // L'id du véhicule est injecté par `#[FromRouteParameter('vehicle')]`
        // sur la propriété `_vehicleId`. Spatie Data le pose dans le
        // payload (clé snake_case `_vehicle_id`) avant l'évaluation des
        // règles, ce qui permet à `Rule::unique` de l'exclure de la
        // recherche d'unicité sur `license_plate`.
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
            'change_reason.in' => 'Motif invalide pour une nouvelle version.',
            'change_note.required' => 'La note est obligatoire pour le motif « Autre changement ».',
        ];
    }
}
