<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Control\RecipientLevel;
use App\Enums\Control\RecipientOperation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Single include/exclude reminder-recipient delta (Chantier B). Resolved on the
 * fly into the effective recipient list (never materialised); see
 * {@see RecipientLevel} for the cascade. B1 persists the `settings` (level 0
 * defaults) and `definition` (level 1) levels; the `vehicle` level lands in B2.
 *
 * @property int $id
 * @property RecipientLevel $level
 * @property int|null $control_definition_id
 * @property int|null $vehicle_control_override_id
 * @property RecipientOperation $operation
 * @property string $email
 * @property string|null $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'level',
    'control_definition_id',
    'vehicle_control_override_id',
    'operation',
    'email',
    'name',
])]
final class ControlRecipientDelta extends Model
{
    protected $table = 'control_recipient_deltas';

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
            'level' => RecipientLevel::class,
            'operation' => RecipientOperation::class,
            'control_definition_id' => 'integer',
            'vehicle_control_override_id' => 'integer',
        ];
    }
}
