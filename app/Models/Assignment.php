<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Attribution d'un véhicule à une entreprise sur un jour donné — entité
 * pivot du modèle Floty (ADR-0005 : granularité jour).
 *
 * Cf. 01-schema-metier.md § 6.
 *
 * **Invariant critique** (CDC § 2.4) : un véhicule ne peut être attribué
 * qu'à une seule entreprise par jour. Garanti par l'UNIQUE sur la colonne
 * générée `vehicle_date_active` + validation applicative.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $company_id
 * @property int|null $driver_id
 * @property Carbon $date
 * @property int $date_year
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'vehicle_id',
    'company_id',
    'driver_id',
    'date',
])]
final class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Conducteur désigné, optionnel à la création.
     *
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
