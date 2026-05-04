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
 * Payload d'édition d'une VFC isolée depuis la modale Historique.
 *
 * Contrairement à `UpdateVehicleData` qui orchestre identité +
 * mode (correction/nouvelle version), ce DTO porte uniquement
 * les champs d'une VFC : valeurs fiscales + bornes
 * `effectiveFrom`/`effectiveTo` + motif/note.
 *
 * Les invariants inter-versions (anti-chevauchement, comblement
 * automatique des trous adjacents, restrictions courante↔historique)
 * sont validés par {@see UpdateFiscalCharacteristicsAction} qui
 * connaît l'historique complet du véhicule.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateFiscalCharacteristicsData extends Data
{
    public function __construct(
        // Bornes (peut transformer un historique → courante uniquement
        // si le véhicule n'a pas déjà une courante - vérification à
        // l'Action).
        #[Required, Date]
        public string $effectiveFrom,

        #[Date]
        public ?string $effectiveTo,

        // Champs fiscaux
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

        // Spécificités fiscales (toujours visibles)
        #[IntegerType, Min(0), Max(10000)]
        public ?int $kerbMass = null,

        public bool $handicapAccess = false,

        // Usage spécifique (conditionnels selon catégorie/carrosserie)
        public bool $m1SpecialUse = false,

        public bool $n1PassengerTransport = false,

        public bool $n1RemovableSecondRowSeat = false,

        public bool $n1SkiLiftUse = false,

        // Motif (peut être modifié pour corriger la classification
        // historique). `InitialCreation` reste réservé au système.
        #[Required]
        public FiscalCharacteristicsChangeReason $changeReason = FiscalCharacteristicsChangeReason::Recharacterization,

        #[Max(2000)]
        public ?string $changeNote = null,

        // Confirmation explicite de la cascade destructive (suppression
        // d'au moins une autre VFC voisine). Posé à `true` côté front
        // après que l'utilisateur a validé la modale de confirmation.
        // Si la cascade n'est pas destructive, ce champ est ignoré.
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'co2_wltp.required' => 'Le CO₂ WLTP est obligatoire quand la méthode d\'homologation est WLTP.',
            'co2_nedc.required' => 'Le CO₂ NEDC est obligatoire quand la méthode d\'homologation est NEDC.',
            'taxable_horsepower.required' => 'La puissance administrative est obligatoire quand la méthode d\'homologation est PA.',
            'underlying_combustion_engine_type.required' => 'Le type de moteur thermique sous-jacent est obligatoire pour les véhicules hybrides.',
            'change_note.required' => 'La note est obligatoire pour le motif « Autre changement ».',
        ];
    }
}
