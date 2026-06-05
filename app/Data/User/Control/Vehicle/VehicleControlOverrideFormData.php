<?php

declare(strict_types=1);

namespace App\Data\User\Control\Vehicle;

use App\Data\User\Control\ControlRecipientData;
use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Enums\Control\VehicleControlStatus;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-vehicle control editor payload (Chantier B). One DTO for both:
 *   - an override of a GLOBAL control (`controlDefinitionId` set): the editor is
 *     a full pre-filled form (no per-section toggle); the action persists a field
 *     only if it DIFFERS from the global (else NULL = keep inheriting), so the
 *     name is never overridden here ;
 *   - a vehicle-SPECIFIC control (`controlDefinitionId` null): the échéance
 *     recipe (name included) is always required and stored as-is.
 *
 * The vehicle id comes from the route, not the payload. Recipients are the
 * level-2 deltas (own additions + inherited exclusions).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class VehicleControlOverrideFormData extends Data
{
    /**
     * @param  array<int, ControlRecipientData>  $ownRecipients
     * @param  array<int, string>  $excludedDefaultEmails
     */
    public function __construct(
        public ?int $controlDefinitionId = null,
        public VehicleControlStatus $status = VehicleControlStatus::Active,
        public ?string $name = null,
        public ?ControlAnchor $anchor = null,
        public ?int $initialDurationValue = null,
        public ?DurationUnit $initialDurationUnit = null,
        public ?int $cycleValue = null,
        public ?DurationUnit $cycleUnit = null,
        public bool $notifyDriver = false,
        public bool $impliesUnavailability = false,
        public bool $customizeReminders = false,
        public ?int $reminderDaysBefore = null,
        public ?bool $reminderOnDueDay = null,
        public ?int $reminderRepeatEveryDays = null,
        #[DataCollectionOf(ControlRecipientData::class)]
        public array $ownRecipients = [],
        public array $excludedDefaultEmails = [],
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $isSpecific = ($payload['control_definition_id'] ?? null) === null;

        // The recipe is always present in the form (pre-filled for an override).
        // Only the NAME differs: required for a specific control, never editable
        // for an override (it inherits the global).
        $rules = [
            'name' => $isSpecific ? ['required', 'string', 'max:120'] : ['nullable', 'string', 'max:120'],
            'anchor' => ['required', new Enum(ControlAnchor::class)],
            'initial_duration_value' => ['required', 'integer', 'min:1', 'max:600'],
            'initial_duration_unit' => ['required', new Enum(DurationUnit::class)],
            'cycle_value' => ['required', 'integer', 'min:1', 'max:600'],
            'cycle_unit' => ['required', new Enum(DurationUnit::class)],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reminder_on_due_day' => ['nullable', 'boolean'],
            'reminder_repeat_every_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'excluded_default_emails' => ['nullable', 'array'],
            'excluded_default_emails.*' => ['email', 'max:180'],
        ];

        if ((bool) ($payload['customize_reminders'] ?? false)) {
            $rules['reminder_days_before'] = ['required', 'integer', 'min:0', 'max:365'];
            $rules['reminder_on_due_day'] = ['required', 'boolean'];
            $rules['reminder_repeat_every_days'] = ['required', 'integer', 'min:1', 'max:365'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Le nom du contrôle est obligatoire.',
            'anchor.required' => 'La date de référence (ancre) est obligatoire.',
            'initial_duration_value.required' => 'La durée de validité initiale est obligatoire.',
            'initial_duration_value.min' => 'La durée de validité initiale doit être positive.',
            'initial_duration_unit.required' => "L'unité de la validité initiale est obligatoire.",
            'cycle_value.required' => 'La périodicité est obligatoire.',
            'cycle_value.min' => 'La périodicité doit être positive.',
            'cycle_unit.required' => "L'unité de la périodicité est obligatoire.",
            'reminder_days_before.required' => 'Indiquez le nombre de jours avant échéance.',
            'reminder_on_due_day.required' => 'Précisez si un rappel est envoyé le jour J.',
            'reminder_repeat_every_days.required' => 'Indiquez la fréquence de répétition après échéance.',
            'reminder_repeat_every_days.min' => 'La répétition doit être d\'au moins 1 jour.',
            'excluded_default_emails.*.email' => "Une adresse de destinataire retiré n'est pas valide.",
        ];
    }
}
