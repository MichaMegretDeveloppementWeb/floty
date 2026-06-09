<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ControlExecutionObserver;
use Database\Factories\ControlExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One recorded execution ("Fait") of a control on a vehicle (Chantier B / B2):
 * date (retroactive allowed), optional note, documents, and a link to the
 * generated vehicle event (Chantier A). Targets either a global definition or a
 * vehicle-specific override (CHECK). Soft-deleted legal history.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int|null $control_definition_id
 * @property int|null $vehicle_control_override_id
 * @property int|null $vehicle_event_id
 * @property Carbon $executed_on
 * @property string|null $note
 * @property int $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'vehicle_id',
    'control_definition_id',
    'vehicle_control_override_id',
    'vehicle_event_id',
    'executed_on',
    'note',
    'recorded_by',
])]
#[ObservedBy([ControlExecutionObserver::class])]
final class ControlExecution extends Model
{
    /** @use HasFactory<ControlExecutionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'control_executions';

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
     * @return BelongsTo<VehicleEvent, $this>
     */
    public function vehicleEvent(): BelongsTo
    {
        return $this->belongsTo(VehicleEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<ControlExecutionDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ControlExecutionDocument::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_on' => 'date',
        ];
    }
}
