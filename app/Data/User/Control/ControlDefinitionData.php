<?php

declare(strict_types=1);

namespace App\Data\User\Control;

use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Enums\Control\RecipientOperation;
use App\Models\ControlDefinition;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Read projection of a global control definition for the catalog + inline
 * editor (Chantier B / B1). `customizeReminders` is derived (a definition
 * customises its reminder cycle when its `reminder_days_before` is set);
 * recipient deltas are split into the definition's own additions
 * (`ownRecipients`, level 1 includes) and the inherited defaults it removes
 * (`excludedDefaultEmails`, level 1 excludes).
 */
#[TypeScript]
final class ControlDefinitionData extends Data
{
    /**
     * @param  array<int, ControlRecipientData>  $ownRecipients
     * @param  array<int, string>  $excludedDefaultEmails
     */
    public function __construct(
        public int $id,
        public string $name,
        public ControlAnchor $anchor,
        public int $initialDurationValue,
        public DurationUnit $initialDurationUnit,
        public int $cycleValue,
        public DurationUnit $cycleUnit,
        public bool $notifyDriver,
        public bool $impliesUnavailability,
        public bool $customizeReminders,
        public ?int $reminderDaysBefore,
        public ?bool $reminderOnDueDay,
        public ?int $reminderRepeatEveryDays,
        public bool $isActive,
        public int $displayOrder,
        #[DataCollectionOf(ControlRecipientData::class)]
        public array $ownRecipients,
        public array $excludedDefaultEmails,
    ) {}

    public static function fromModel(ControlDefinition $definition): self
    {
        $deltas = $definition->recipientDeltas;

        $ownRecipients = $deltas
            ->where('operation', RecipientOperation::Include)
            ->map(static fn ($delta): ControlRecipientData => new ControlRecipientData(
                name: (string) $delta->name,
                email: $delta->email,
            ))
            ->values()
            ->all();

        $excludedDefaultEmails = $deltas
            ->where('operation', RecipientOperation::Exclude)
            ->pluck('email')
            ->values()
            ->all();

        return new self(
            id: $definition->id,
            name: $definition->name,
            anchor: $definition->anchor,
            initialDurationValue: $definition->initial_duration_value,
            initialDurationUnit: $definition->initial_duration_unit,
            cycleValue: $definition->cycle_value,
            cycleUnit: $definition->cycle_unit,
            notifyDriver: $definition->notify_driver,
            impliesUnavailability: $definition->implies_unavailability,
            customizeReminders: $definition->reminder_days_before !== null,
            reminderDaysBefore: $definition->reminder_days_before,
            reminderOnDueDay: $definition->reminder_on_due_day,
            reminderRepeatEveryDays: $definition->reminder_repeat_every_days,
            isActive: $definition->is_active,
            displayOrder: $definition->display_order,
            ownRecipients: $ownRecipients,
            excludedDefaultEmails: $excludedDefaultEmails,
        );
    }
}
