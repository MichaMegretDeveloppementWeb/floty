<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\VehicleYearlyPricingObserver;
use Database\Factories\VehicleYearlyPricingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Daily / weekly / monthly rental rates for a vehicle on a given year.
 *
 * One row per `(vehicle_id, year)` pair (DB UNIQUE constraint). Repositories
 * guarantee idempotence via `updateOrCreate`.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $year
 * @property int $daily_rate_cents
 * @property int $weekly_rate_cents
 * @property int $monthly_rate_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vehicle $vehicle
 */
#[Fillable([
    'vehicle_id',
    'year',
    'daily_rate_cents',
    'weekly_rate_cents',
    'monthly_rate_cents',
])]
#[ObservedBy([VehicleYearlyPricingObserver::class])]
final class VehicleYearlyPricing extends Model
{
    /** @use HasFactory<VehicleYearlyPricingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'daily_rate_cents' => 'integer',
            'weekly_rate_cents' => 'integer',
            'monthly_rate_cents' => 'integer',
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
}
