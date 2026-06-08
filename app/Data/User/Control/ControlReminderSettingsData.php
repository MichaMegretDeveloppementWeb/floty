<?php

declare(strict_types=1);

namespace App\Data\User\Control;

use App\Models\ControlReminderSettings;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Global default reminder configuration (Chantier B / B1). Carries the default
 * reminder cycle and the level-0 default recipient list (persisted as `settings`
 * include deltas). Serves both the read settings page and the HTTP write endpoint.
 *
 * `daysBefore` may be 0 (remind only from the due day on); `repeatEveryDays`
 * must stay positive (it drives the post-échéance repetition).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class ControlReminderSettingsData extends Data
{
    /**
     * @param  array<int, ControlRecipientData>  $defaultRecipients
     */
    public function __construct(
        #[Min(0), Max(365)]
        public int $daysBefore,
        public bool $remindOnDueDay,
        #[Min(1), Max(365)]
        public int $repeatEveryDays,
        #[DataCollectionOf(ControlRecipientData::class)]
        public array $defaultRecipients,
    ) {}

    /**
     * @param  array<int, ControlRecipientData>  $defaultRecipients
     */
    public static function fromModel(ControlReminderSettings $settings, array $defaultRecipients): self
    {
        return new self(
            daysBefore: $settings->days_before,
            remindOnDueDay: $settings->remind_on_due_day,
            repeatEveryDays: $settings->repeat_every_days,
            defaultRecipients: $defaultRecipients,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'days_before.min' => 'Le nombre de jours avant échéance ne peut pas être négatif.',
            'days_before.max' => 'Le nombre de jours avant échéance est trop élevé (365 maximum).',
            'repeat_every_days.min' => 'La répétition doit être d\'au moins 1 jour.',
            'repeat_every_days.max' => 'La répétition est trop élevée (365 jours maximum).',
        ];
    }
}
