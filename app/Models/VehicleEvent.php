<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VehicleEvent\VehicleEventSystemKind;
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
 * The identity is the free name (`title`) + the natures (free text, child
 * rows in {@see VehicleEventCategory}, labelled « Nature » in the UI). Two
 * orthogonal axes:
 *   - `implies_unavailability` : informative "the vehicle is unavailable these
 *     days" flag (heatmap / usage / timeline / exit). User choice, but forced
 *     true when the event is fiscally reductive (SQL CHECK as backstop).
 *   - `has_fiscal_impact` : fiscal prorata reducer (ADR-0016 rev. 1.1),
 *     denormalized at write time from the reductive natures of the catalogue
 *     ({@see App\Services\VehicleEvent\EventNatureFiscalResolver}) and frozen
 *     on the row; the fiscal rules (R-20XX-008) read only this boolean.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property VehicleEventSystemKind|null $system_kind
 * @property string $title
 * @property bool $has_fiscal_impact
 * @property bool $implies_unavailability
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string|null $description
 * @property int|null $amount_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'vehicle_id',
    'system_kind',
    'title',
    'has_fiscal_impact',
    'implies_unavailability',
    'start_date',
    'end_date',
    'description',
    'amount_cents',
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
            'system_kind' => VehicleEventSystemKind::class,
            'has_fiscal_impact' => 'boolean',
            'implies_unavailability' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'amount_cents' => 'integer',
        ];
    }

    /**
     * Whether this is a system-generated lifecycle event (acquisition, fleet
     * exit) · read-only, not manually editable or deletable.
     */
    public function isSystemGenerated(): bool
    {
        return $this->system_kind !== null;
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

    /**
     * Attached natures (free text + catalogue suggestions, at least one),
     * ordered by insertion. Kept under the `categories` naming in code.
     *
     * @return HasMany<VehicleEventCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(VehicleEventCategory::class)->orderBy('id');
    }
}
