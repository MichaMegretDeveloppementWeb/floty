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
 * Per-vehicle control editor payload (Chantier B / B2). One DTO for both:
 *   - an override of a GLOBAL control (`controlDefinitionId` set): each section
 *     is sparse, gated by its `customize*` toggle (off = inherit the global,
 *     persisted as NULL) ;
 *   - a vehicle-SPECIFIC control (`controlDefinitionId` null): the échéance
 *     recipe is always required.
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
        public bool $customizeSchedule = false,
        public ?string $name = null,
        public ?ControlAnchor $anchor = null,
        public ?int $initialDurationValue = null,
        public ?DurationUnit $initialDurationUnit = null,
        public ?int $cycleValue = null,
        public ?DurationUnit $cycleUnit = null,
        public bool $customizeBehaviour = false,
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
        $recipeRequired = $isSpecific || (bool) ($payload['customize_schedule'] ?? false);

        $rules = [
            'name' => $recipeRequired ? ['required', 'string', 'max:120'] : ['nullable', 'string', 'max:120'],
            'initial_duration_value' => $recipeRequired ? ['required', 'integer', 'min:1', 'max:600'] : ['nullable', 'integer', 'min:1', 'max:600'],
            'cycle_value' => $recipeRequired ? ['required', 'integer', 'min:1', 'max:600'] : ['nullable', 'integer', 'min:1', 'max:600'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reminder_on_due_day' => ['nullable', 'boolean'],
            'reminder_repeat_every_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'excluded_default_emails' => ['nullable', 'array'],
            'excluded_default_emails.*' => ['email', 'max:180'],
        ];

        if ($recipeRequired) {
            $rules['anchor'] = ['required', new Enum(ControlAnchor::class)];
            $rules['initial_duration_unit'] = ['required', new Enum(DurationUnit::class)];
            $rules['cycle_unit'] = ['required', new Enum(DurationUnit::class)];
        }

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
