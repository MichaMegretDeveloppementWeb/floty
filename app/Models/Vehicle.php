<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use Carbon\CarbonInterface;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Véhicule (attributs non fiscaux - les caractéristiques fiscalement
 * déterminantes sont dans {@see VehicleFiscalCharacteristics}).
 *
 * Cf. 01-schema-metier.md § 2.
 *
 * @property int $id
 * @property bool $is_exited Computed accessor : true ssi exit_date IS NOT NULL
 * @property string $license_plate
 * @property string $brand
 * @property string $model
 * @property string|null $vin
 * @property string|null $color
 * @property string|null $photo_path
 * @property Carbon $first_french_registration_date
 * @property Carbon $first_origin_registration_date
 * @property Carbon $first_economic_use_date
 * @property Carbon $acquisition_date
 * @property Carbon|null $exit_date
 * @property VehicleExitReason|null $exit_reason
 * @property VehicleStatus $current_status
 * @property int|null $mileage_current
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'license_plate',
    'brand',
    'model',
    'vin',
    'color',
    'photo_path',
    'first_french_registration_date',
    'first_origin_registration_date',
    'first_economic_use_date',
    'acquisition_date',
    'exit_date',
    'exit_reason',
    'current_status',
    'mileage_current',
    'notes',
])]
final class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_french_registration_date' => 'date',
            'first_origin_registration_date' => 'date',
            'first_economic_use_date' => 'date',
            'acquisition_date' => 'date',
            'exit_date' => 'date',
            'exit_reason' => VehicleExitReason::class,
            'current_status' => VehicleStatus::class,
        ];
    }

    /**
     * Chaîne historisée des caractéristiques fiscales (périodes qui ne se
     * chevauchent jamais).
     *
     * @return HasMany<VehicleFiscalCharacteristics, $this>
     */
    public function fiscalCharacteristics(): HasMany
    {
        return $this->hasMany(VehicleFiscalCharacteristics::class);
    }

    /**
     * Contrats du véhicule (entité pivot post ADR-0014).
     *
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Indisponibilités du véhicule.
     *
     * @return HasMany<Unavailability, $this>
     */
    public function unavailabilities(): HasMany
    {
        return $this->hasMany(Unavailability::class);
    }

    /**
     * Vrai ssi le véhicule est sorti de flotte (`exit_date IS NOT NULL`).
     * Sémantique purement booléenne ; pour les filtrations applicatives
     * **toujours préférer les scopes date-aware** ({@see scopeActiveAt},
     * {@see scopeActiveDuring}) - un véhicule sorti reste pleinement
     * opérationnel sur sa période d'activité antérieure (cf. ADR-0018 D3).
     *
     * @return Attribute<bool, never>
     */
    protected function isExited(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->exit_date !== null,
        );
    }

    /**
     * Scope : véhicules actifs **à la date donnée** (jamais sortis ou
     * sortis postérieurement à cette date).
     *
     * Critère : `exit_date IS NULL OR exit_date >= $date`.
     *
     * Utilisé pour les vues "aujourd'hui" (Dashboard, Index Flotte) et
     * pour le filtre annuel via {@see scopeActiveDuring} (qui prend
     * `start_of_year` comme date pivot).
     *
     * Cf. ADR-0018 § 4 (matrice de visibilité date-aware).
     *
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeActiveAt(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where(function (Builder $q) use ($date): void {
            $q->whereNull('exit_date')
                ->orWhere('exit_date', '>=', $date->toDateString());
        });
    }

    /**
     * Scope : véhicules actifs **à un moment quelconque** de la fenêtre
     * `[start, end]`. Utile pour la heatmap année N et pour les calculs
     * fiscaux annuels - un véhicule sorti mi-année reste affiché pour
     * l'année où il était partiellement actif.
     *
     * Critère : `exit_date IS NULL OR exit_date >= $start`.
     * (Si exit_date >= start, le véhicule était actif au moins jusqu'à
     * cette date dans la fenêtre.)
     *
     * Cf. ADR-0018 § 4.
     *
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeActiveDuring(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $this->scopeActiveAt($query, $start);
    }
}
