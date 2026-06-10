<?php

declare(strict_types=1);

namespace App\Data\User\Control\Vehicle;

use App\Rules\Vehicle\AvailableForPeriod;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * "Fait" payload for a control execution (Chantier B / B2). The execution date
 * is deliberately non-restrictive (retroactive entry allowed) but bounded by the
 * vehicle's fleet-exit date (ADR-0018) via {@see AvailableForPeriod}. Targets
 * either a global definition or a vehicle-specific override (the DB CHECK is the
 * backstop for "exactly one"). Documents follow the VehicleEvent limits.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class RecordControlExecutionData extends Data
{
    /**
     * @param  list<string>  $categories  Extra natures added on the "Fait"
     *                                    modal; the Action prepends the auto
     *                                    natures « Contrôle réglementaire » +
     *                                    « Maintenance préventive ». Unlimited.
     */
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,
        #[Required]
        public string $executedOn,
        public ?int $controlDefinitionId = null,
        public ?int $vehicleControlOverrideId = null,
        public ?string $note = null,
        public array $categories = [],
        /** Optional cost (TTC) in cents of the control; costs only. */
        public ?int $amountCents = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;

        $rules = [
            'control_definition_id' => ['nullable', 'integer', 'exists:control_definitions,id', 'required_without:vehicle_control_override_id'],
            'vehicle_control_override_id' => ['nullable', 'integer', 'exists:vehicle_control_overrides,id', 'required_without:control_definition_id'],
            'note' => ['nullable', 'string', 'max:500'],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            // « Contrôle réglementaire » + « Maintenance préventive » sont
            // ajoutés d'office par l'Action ; les ajouts utilisateur sont illimités.
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:60', 'distinct:ignore_case'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];

        $vehicleId = (int) ($payload['vehicle_id'] ?? 0);
        $executedOn = (string) ($payload['executed_on'] ?? '');

        $executedRules = ['required', 'date'];

        if ($vehicleId !== 0 && $executedOn !== '') {
            try {
                $date = CarbonImmutable::parse($executedOn);
                $executedRules[] = new AvailableForPeriod($vehicleId, $date, $date);
            } catch (\Exception) {
                // Leave the base date rule to surface the format error.
            }
        }

        $rules['executed_on'] = $executedRules;

        return $rules;
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
            'vehicle_id.exists' => 'Ce véhicule est introuvable.',
            'executed_on.required' => 'La date de réalisation est obligatoire.',
            'executed_on.date' => 'La date de réalisation doit être une date valide.',
            'control_definition_id.numeric' => 'Contrôle ciblé invalide.',
            'control_definition_id.integer' => 'Contrôle ciblé invalide.',
            'control_definition_id.exists' => 'Contrôle ciblé introuvable.',
            'control_definition_id.required_without' => 'Le contrôle ciblé est obligatoire.',
            'vehicle_control_override_id.numeric' => 'Contrôle ciblé invalide.',
            'vehicle_control_override_id.integer' => 'Contrôle ciblé invalide.',
            'vehicle_control_override_id.exists' => 'Contrôle ciblé introuvable.',
            'vehicle_control_override_id.required_without' => 'Le contrôle ciblé est obligatoire.',
            'note.max' => 'La note ne doit pas dépasser :max caractères.',
            'amount_cents.numeric' => 'Le montant doit être un nombre.',
            'amount_cents.integer' => 'Le montant doit être un nombre entier.',
            'amount_cents.min' => 'Le montant ne peut pas être négatif.',
            'categories.*.max' => 'Une nature ne peut pas dépasser 60 caractères.',
            'categories.*.distinct' => 'Cette nature est déjà présente.',
            'documents.max' => 'Vous ne pouvez joindre que 5 documents au maximum.',
            'documents.*.file' => 'Chaque pièce jointe doit être un fichier valide.',
            'documents.*.mimes' => 'Format invalide · seuls les fichiers PDF, JPG, PNG et WebP sont acceptés.',
            'documents.*.max' => 'Fichier trop volumineux · 5 Mo maximum.',
        ];
    }
}
