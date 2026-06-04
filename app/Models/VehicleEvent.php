<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Observers\VehicleEventObserver;
use Database\Factories\VehicleEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Vehicle event over a continuous day range (formerly "unavailability").
 *
 * Two orthogonal axes:
 *   - `implies_unavailability` : informative "the vehicle is unavailable these
 *     days" flag (heatmap / usage / timeline / exit). Always true for known
 *     types; for the `other` (custom) type it follows the user's choice.
 *   - `has_fiscal_impact` : fiscal prorata reducer (ADR-0016 rev. 1.1, see
 *     {@see VehicleEventType::isFiscallyReductive()}), denormalized with a SQL
 *     CHECK enforcing consistency with `type`. Never true for `other`.
 *
 * `title` / `category` carry the custom identity of an `other` event; known
 * types derive both from the enum on the front (columns stay null).
 *
 * @property int $id
 * @property int $vehicle_id
 * @property VehicleEventType $type
 * @property string|null $title
 * @property string|null $category
 * @property bool $has_fiscal_impact
 * @property bool $implies_unavailability
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'vehicle_id',
    'type',
    'title',
    'category',
    'has_fiscal_impact',
    'implies_unavailability',
    'start_date',
    'end_date',
    'description',
])]
#[ObservedBy([VehicleEventObserver::class])]
final class VehicleEvent extends Model
{
    /** @use HasFactory<VehicleEventFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleEventType::class,
            'has_fiscal_impact' => 'boolean',
            'implies_unavailability' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Restrict to events that mark the vehicle unavailable (informative axis).
     * Used by every "unavailability days" read (heatmap, usage, week grid,
     * exit conflicts); never by the fiscal path, which keys on `has_fiscal_impact`.
     *
     * @param  Builder<VehicleEvent>  $query
     * @return Builder<VehicleEvent>
     */
    public function scopeImpliesUnavailability(Builder $query): Builder
    {
        return $query->where('implies_unavailability', true);
    }

    /**
     * Owning vehicle.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Attached supporting documents (image or PDF, max 5).
     *
     * @return HasMany<VehicleEventDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(VehicleEventDocument::class);
    }
}
