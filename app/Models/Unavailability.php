<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Unavailability\UnavailabilityType;
use App\Observers\UnavailabilityObserver;
use Database\Factories\UnavailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Vehicle unavailability over a continuous day range.
 *
 * Three types reduce the fiscal prorata numerator (ADR-0016 rev. 1.1,
 * see {@see UnavailabilityType::isFiscallyReductive()}). The `has_fiscal_impact`
 * column is denormalized for fast querying; a SQL CHECK enforces consistency with `type`.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property UnavailabilityType $type
 * @property bool $has_fiscal_impact
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
    'has_fiscal_impact',
    'start_date',
    'end_date',
    'description',
])]
#[ObservedBy([UnavailabilityObserver::class])]
final class Unavailability extends Model
{
    /** @use HasFactory<UnavailabilityFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UnavailabilityType::class,
            'has_fiscal_impact' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
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
     * @return HasMany<UnavailabilityDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(UnavailabilityDocument::class);
    }
}
