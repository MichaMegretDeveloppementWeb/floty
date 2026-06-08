<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Actions\Vehicle\CreateFiscalCharacteristicsAction;
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
 * Payload for inserting a new VFC from the History modal. Cross-version
 * invariants (overlap prevention, gap filling, cascade deletion) are handled
 * by {@see CreateFiscalCharacteristicsAction}.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreFiscalCharacteristicsData extends Data
{
    public function __construct(
        #[Required, Date]
        public string $effectiveFrom,

        #[Date]
        public ?string $effectiveTo,

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

        // InitialCreation is reserved for the system path (CreateVehicleAction);
        // user-driven insertions default to Recharacterization.
        #[Required]
        public FiscalCharacteristicsChangeReason $changeReason = FiscalCharacteristicsChangeReason::Recharacterization,

        #[Max(2000)]
        public ?string $changeNote = null,

        // Explicit confirmation of a destructive cascade (swallowing existing
        // versions). Ignored when the cascade is non-destructive.
        public bool $confirmed = false,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $method = $payload['homologation_method'] ?? null;
        $energy = $payload['energy_source'] ?? null;
        $reason = $payload['change_reason'] ?? null;

        $isOther = $reason === FiscalCharacteristicsChangeReason::OtherChange->value;
        $isHybrid = in_array($energy, [
            EnergySource::PluginHybrid->value,
            EnergySource::NonPluginHybrid->value,
            EnergySource::ElectricHydrogen->value,
        ], true);

        return [
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
            'change_note' => [
                Rule::requiredIf(fn (): bool => $isOther),
            ],
            'change_reason' => [
                Rule::notIn([FiscalCharacteristicsChangeReason::InitialCreation->value]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'effective_from.required' => "La date d'effet est obligatoire.",
            'effective_from.date' => "La date d'effet doit être une date valide.",
            'effective_to.date' => "La date de fin d'effet doit être une date valide.",
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
            'change_reason.required' => 'Le motif de changement est obligatoire.',
            'change_reason.enum' => 'Le motif de changement sélectionné est invalide.',
            'change_note.required' => 'La note est obligatoire pour le motif « Autre changement ».',
            'change_note.max' => 'La note ne doit pas dépasser :max caractères.',
            'change_reason.not_in' => 'Le motif « Création initiale » est réservé au système.',
        ];
    }
}
