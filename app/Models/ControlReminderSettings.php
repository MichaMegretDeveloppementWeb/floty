<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ControlReminderSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Global default reminder configuration for regulatory controls (Chantier B / B1).
 * Application singleton (always `id=1`), auto-created with defaults on first access,
 * mirroring {@see FiscalRiskSettings::singleton()}.
 *
 * Holds the default reminder cycle (a control definition may override it) plus the
 * "always notify" recipient scalar, always kept in the resolved recipient list unless
 * an explicit exclude delta removes it. The default recipients themselves (level 0)
 * live in `control_recipient_deltas` (level = settings), not on this row.
 *
 * @property int $id
 * @property int $days_before
 * @property bool $remind_on_due_day
 * @property int $repeat_every_days
 * @property string|null $always_notify_name
 * @property string|null $always_notify_email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'days_before',
    'remind_on_due_day',
    'repeat_every_days',
    'always_notify_name',
    'always_notify_email',
])]
final class ControlReminderSettings extends Model
{
    /** @use HasFactory<ControlReminderSettingsFactory> */
    use HasFactory;

    protected $table = 'control_reminder_settings';

    /**
     * Returns the application singleton row, auto-creating it with defaults on first access.
     *
     * Defaults are materialized in PHP (not delegated to SQL column defaults) to stay
     * portable across drivers, same race-safe pattern as {@see FiscalRiskSettings::singleton()}.
     */
    public static function singleton(): self
    {
        return self::unguarded(fn (): self => self::query()->firstOrCreate(['id' => 1], [
            'days_before' => 15,
            'remind_on_due_day' => true,
            'repeat_every_days' => 5,
            'always_notify_name' => null,
            'always_notify_email' => null,
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_before' => 'integer',
            'remind_on_due_day' => 'boolean',
            'repeat_every_days' => 'integer',
        ];
    }
}
