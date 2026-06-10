<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One free detail line attached to a vehicle event (section « Détails »:
 * « Vidange », « Changement courroie »...). Free text with autocomplete from
 * {@see VehicleEventDetailSuggestion}; `UNIQUE(vehicle_event_id, detail)`
 * enforces intra-event dedup.
 *
 * @property int $id
 * @property int $vehicle_event_id
 * @property string $detail
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'vehicle_event_id',
    'detail',
])]
final class VehicleEventDetail extends Model
{
    protected $table = 'vehicle_event_details';

    /**
     * Owning event.
     *
     * @return BelongsTo<VehicleEvent, $this>
     */
    public function vehicleEvent(): BelongsTo
    {
        return $this->belongsTo(VehicleEvent::class);
    }
}
