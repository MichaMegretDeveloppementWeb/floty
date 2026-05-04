<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Unavailability\UnavailabilityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Indisponibilité d'un véhicule sur une plage continue de jours.
 *
 * Cf. 01-schema-metier.md § 7.
 *
 * Trois types réduisent le numérateur du prorata fiscal - cf. ADR-0016
 * rev. 1.1 et {@see UnavailabilityType::isFiscallyReductive()}. La colonne
 * `has_fiscal_impact` est dénormalisée pour un requêtage rapide ;
 * un CHECK SQL garantit la cohérence avec `type`.
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
final class Unavailability extends Model
{
    use HasFactory, SoftDeletes;

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
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
