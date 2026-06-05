<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Control\ReminderKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Idempotence journal row for a fired control reminder occurrence (Chantier B / B3).
 * `target_key` ('def:{id}' / 'ovr:{id}') is the non-null dedup key (see migration).
 * Append-only.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int|null $control_definition_id
 * @property int|null $vehicle_control_override_id
 * @property string $target_key
 * @property Carbon $due_on
 * @property Carbon $reminder_on
 * @property ReminderKind $kind
 * @property int $recipients_count
 * @property Carbon $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'vehicle_id',
    'control_definition_id',
    'vehicle_control_override_id',
    'target_key',
    'due_on',
    'reminder_on',
    'kind',
    'recipients_count',
    'sent_at',
])]
final class ControlReminderLog extends Model
{
    protected $table = 'control_reminder_logs';

    /**
     * Non-null dedup key for a control target ('def:{id}' / 'ovr:{id}').
     */
    public static function targetKey(?int $definitionId, ?int $overrideId): string
    {
        return $definitionId !== null
            ? 'def:'.$definitionId
            : 'ovr:'.$overrideId;
    }

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
     * @return BelongsTo<VehicleControlOverride, $this>
     */
    public function vehicleControlOverride(): BelongsTo
    {
        return $this->belongsTo(VehicleControlOverride::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'reminder_on' => 'date',
            'kind' => ReminderKind::class,
            'recipients_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
