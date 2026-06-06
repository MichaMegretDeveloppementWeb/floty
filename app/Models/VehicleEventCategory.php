<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VehicleEventCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One category attached to a vehicle event (Chantier A, multi categories,
 * up to 5 per event). Free text with autocomplete suggestions; stored as
 * normalized rows (one per category) so future reporting can filter by
 * category in SQL ("tous les événements de catégorie X", combined with type /
 * year). `UNIQUE(vehicle_event_id, category)` enforces intra-event dedup;
 * the index on `category` serves the filtering.
 *
 * @property int $id
 * @property int $vehicle_event_id
 * @property string $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'vehicle_event_id',
    'category',
])]
final class VehicleEventCategory extends Model
{
    /** @use HasFactory<VehicleEventCategoryFactory> */
    use HasFactory;

    protected $table = 'vehicle_event_categories';

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
