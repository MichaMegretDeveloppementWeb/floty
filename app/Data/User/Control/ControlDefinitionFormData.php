<?php

declare(strict_types=1);

namespace App\Data\User\Control;

use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Create / update payload for a global control definition (Chantier B / B1).
 * The create and edit forms are structurally identical (no server-derived or
 * immutable field differs between them), so a single validated DTO backs both
 * endpoints.
 *
 * Reminder cycle override: when `customizeReminders` is false the three
 * `reminder*` fields are persisted as NULL (inherit the global settings); when
 * true they become required (handled in {@see self::rules()}). Recipients split
 * into the control's own additions (`ownRecipients`, includes) and the
 * inherited defaults it removes (`excludedDefaultEmails`, excludes).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class ControlDefinitionFormData extends Data
{
    /**
     * @param  array<int, ControlRecipientData>  $ownRecipients
     * @param  array<int, string>  $excludedDefaultEmails
     */
    public function __construct(
        #[Required, StringType, Max(120)]
        public string $name,
        #[Required]
        public ControlAnchor $anchor,
        #[Required, IntegerType, Min(1), Max(600)]
        public int $initialDurationValue,
        #[Required]
        public DurationUnit $initialDurationUnit,
        #[Required, IntegerType, Min(1), Max(600)]
        public int $cycleValue,
        #[Required]
        public DurationUnit $cycleUnit,
        public bool $notifyDriver = false,
        public bool $impliesUnavailability = false,
        public bool $isActive = true,
        public bool $customizeReminders = false,
        public ?int $reminderDaysBefore = null,
        public ?bool $reminderOnDueDay = null,
        public ?int $reminderRepeatEveryDays = null,
        #[DataCollectionOf(ControlRecipientData::class)]
        public array $ownRecipients = [],
        public array $excludedDefaultEmails = [],
    ) {}

    /**
     * The reminder override fields become required only when the control opts
     * into customising its reminder cycle.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        // `own_recipients` validation is inferred from its DataCollectionOf
        // (each item is a validated ControlRecipientData); only the scalar /
        // string-list rules need to be stated here.
        $rules = [
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reminder_on_due_day' => ['nullable', 'boolean'],
            'reminder_repeat_every_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'excluded_default_emails' => ['nullable', 'array'],
            'excluded_default_emails.*' => ['email', 'max:180'],
        ];

        if ((bool) ($context->payload['customize_reminders'] ?? false)) {
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
            'name.max' => 'Le nom du contrôle ne doit pas dépasser :max caractères.',
            'anchor.required' => 'La date de référence (ancre) est obligatoire.',
            'anchor.enum' => 'La date de référence (ancre) sélectionnée est invalide.',
            'initial_duration_value.required' => 'La durée de validité initiale est obligatoire.',
            'initial_duration_value.numeric' => 'La durée de validité initiale doit être un nombre.',
            'initial_duration_value.integer' => 'La durée de validité initiale doit être un nombre entier.',
            'initial_duration_value.min' => 'La durée de validité initiale doit être positive.',
            'initial_duration_value.max' => 'La durée de validité initiale ne peut pas dépasser :max.',
            'initial_duration_unit.required' => "L'unité de la validité initiale est obligatoire.",
            'initial_duration_unit.enum' => "L'unité de la validité initiale sélectionnée est invalide.",
            'cycle_value.required' => 'La périodicité est obligatoire.',
            'cycle_value.numeric' => 'La périodicité doit être un nombre.',
            'cycle_value.integer' => 'La périodicité doit être un nombre entier.',
            'cycle_value.min' => 'La périodicité doit être positive.',
            'cycle_value.max' => 'La périodicité ne peut pas dépasser :max.',
            'cycle_unit.required' => "L'unité de la périodicité est obligatoire.",
            'cycle_unit.enum' => "L'unité de la périodicité sélectionnée est invalide.",
            'reminder_days_before.required' => 'Indiquez le nombre de jours avant échéance.',
            'reminder_days_before.integer' => 'Le nombre de jours avant échéance doit être un nombre entier.',
            'reminder_days_before.min' => 'Le nombre de jours avant échéance ne peut pas être négatif.',
            'reminder_days_before.max' => 'Le nombre de jours avant échéance ne peut pas dépasser :max.',
            'reminder_on_due_day.required' => 'Précisez si un rappel est envoyé le jour J.',
            'reminder_repeat_every_days.required' => 'Indiquez la fréquence de répétition après échéance.',
            'reminder_repeat_every_days.integer' => 'La fréquence de répétition doit être un nombre entier.',
            'reminder_repeat_every_days.min' => 'La répétition doit être d\'au moins 1 jour.',
            'reminder_repeat_every_days.max' => 'La fréquence de répétition ne peut pas dépasser :max.',
            'excluded_default_emails.*.email' => "Une adresse de destinataire retiré n'est pas valide.",
            'excluded_default_emails.*.max' => 'Une adresse de destinataire retiré ne doit pas dépasser :max caractères.',
        ];
    }
}
