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
 * Tarifs jour/semaine/mois d'un véhicule pour une année donnée. Pierre
 * angulaire du module Facturation (Phase 14 V1.2).
 *
 * Une seule ligne par couple (vehicle_id, year) — contrainte UNIQUE en
 * base, idempotence garantie via `updateOrCreate` côté repository.
 *
 * Cf. ADR-0006 (architecture du moteur fiscal), spec Phase 14
 * (`project-management/plan-implementation/tasks/phase-14-billing/`),
 * mémoire `roadmap_v12_facturation`.
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
     * Véhicule de rattachement.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
