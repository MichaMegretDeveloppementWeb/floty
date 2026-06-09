<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Observers\ControlDefinitionObserver;
use Database\Factories\ControlDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Global regulatory control definition (Chantier B / B1): a control applied by
 * default to the whole fleet (contrôle technique, pollution, or any future one
 * added without code change). Carries its échéance recipe (anchor + initial
 * validity + recurring cycle), behaviour flags, and an optional per-definition
 * reminder cycle override (NULL fields inherit {@see ControlReminderSettings}).
 *
 * Soft-deleted; recipient deltas (level = definition) hang off `recipientDeltas`.
 *
 * @property int $id
 * @property string $name
 * @property ControlAnchor $anchor
 * @property int $initial_duration_value
 * @property DurationUnit $initial_duration_unit
 * @property int $cycle_value
 * @property DurationUnit $cycle_unit
 * @property bool $notify_driver
 * @property bool $implies_unavailability
 * @property int|null $reminder_days_before
 * @property bool|null $reminder_on_due_day
 * @property int|null $reminder_repeat_every_days
 * @property bool $is_active
 * @property int $display_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name',
    'anchor',
    'initial_duration_value',
    'initial_duration_unit',
    'cycle_value',
    'cycle_unit',
    'notify_driver',
    'implies_unavailability',
    'reminder_days_before',
    'reminder_on_due_day',
    'reminder_repeat_every_days',
    'is_active',
    'display_order',
])]
#[ObservedBy([ControlDefinitionObserver::class])]
final class ControlDefinition extends Model
{
    /** @use HasFactory<ControlDefinitionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'control_definitions';

    /**
     * Recipient deltas owned by this definition (level = definition). Every row
     * with a `control_definition_id` is a definition-level delta by the schema's
     * scope CHECK, so no extra level filter is needed.
     *
     * @return HasMany<ControlRecipientDelta, $this>
     */
    public function recipientDeltas(): HasMany
    {
        return $this->hasMany(ControlRecipientDelta::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anchor' => ControlAnchor::class,
            'initial_duration_value' => 'integer',
            'initial_duration_unit' => DurationUnit::class,
            'cycle_value' => 'integer',
            'cycle_unit' => DurationUnit::class,
            'notify_driver' => 'boolean',
            'implies_unavailability' => 'boolean',
            'reminder_days_before' => 'integer',
            'reminder_on_due_day' => 'boolean',
            'reminder_repeat_every_days' => 'integer',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
