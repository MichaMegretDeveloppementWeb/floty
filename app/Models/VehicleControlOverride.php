<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Enums\Control\VehicleControlStatus;
use Database\Factories\VehicleControlOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Per-vehicle control row (Chantier B / B2). Two kinds, discriminated by
 * `control_definition_id`: an override of a GLOBAL definition (sparse, null
 * fields inherit the global) when set, or a vehicle-SPECIFIC control (complete
 * recipe) when null. Vehicle-level recipient deltas (level=vehicle) hang off
 * `recipientDeltas`.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int|null $control_definition_id
 * @property VehicleControlStatus $status
 * @property string|null $name
 * @property ControlAnchor|null $anchor
 * @property int|null $initial_duration_value
 * @property DurationUnit|null $initial_duration_unit
 * @property int|null $cycle_value
 * @property DurationUnit|null $cycle_unit
 * @property bool|null $notify_driver
 * @property bool|null $implies_unavailability
 * @property int|null $reminder_days_before
 * @property bool|null $reminder_on_due_day
 * @property int|null $reminder_repeat_every_days
 * @property int $display_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'vehicle_id',
    'control_definition_id',
    'status',
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
    'display_order',
])]
final class VehicleControlOverride extends Model
{
    /** @use HasFactory<VehicleControlOverrideFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'vehicle_control_overrides';

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<ControlDefinition, $this>
     */
    public function controlDefinition(): BelongsTo
    {
        return $this->belongsTo(ControlDefinition::class);
    }

    /**
     * Vehicle-level recipient deltas. Every row pointing at this override is a
     * level=vehicle delta by the schema's scope CHECK.
     *
     * @return HasMany<ControlRecipientDelta, $this>
     */
    public function recipientDeltas(): HasMany
    {
        return $this->hasMany(ControlRecipientDelta::class);
    }

    /**
     * @return HasMany<ControlExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(ControlExecution::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VehicleControlStatus::class,
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
            'display_order' => 'integer',
        ];
    }
}
