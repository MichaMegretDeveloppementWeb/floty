<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Contract\ContractType;
use App\Services\Contract\ContractQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Contrat de location véhicule × entreprise sur une plage temporelle
 * inclusive `[start_date, end_date]`. Entité pivot du domaine fiscal
 * post-refonte (cf. ADR-0014 « Modèle Contract et règle LCD par
 * contrat individuel »).
 *
 * Cf. `taxes-rules/2024.md` v2.0 R-2024-021 pour la mécanique
 * d'exonération LCD et `database/migrations/2026_04_29_140000_create_contracts_table.php`
 * pour la structure DB.
 *
 * **Invariants critiques** (matérialisés en DB) :
 *   - `end_date >= start_date` (CHECK SQL)
 *   - Pas deux contrats actifs chevauchants sur le même véhicule
 *     (triggers MySQL `contracts_no_overlap_*`)
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $company_id
 * @property int|null $driver_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string|null $contract_reference
 * @property ContractType $contract_type
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'vehicle_id',
    'company_id',
    'driver_id',
    'start_date',
    'end_date',
    'contract_reference',
    'contract_type',
    'notes',
])]
final class Contract extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'contract_type' => ContractType::class,
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
     * Conducteur désigné, optionnel à la création (cf. phase 06).
     *
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Expansion du contrat en liste de dates ISO (Y-m-d), bornée à
     * l'année passée en argument. Inclut les deux bornes du contrat.
     *
     * Helper réutilisé par les règles fiscales (R-2024-002 numérateur
     * du prorata, R-2024-021 qualification LCD per-contract,
     * R-2024-008 jours indispos réductrices ∩ contrats taxables) et
     * par {@see ContractQueryService}.
     *
     * @return list<string>
     */
    public function expandToDaysInYear(int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $start = CarbonImmutable::parse($this->start_date->toDateString());
        $end = CarbonImmutable::parse($this->end_date->toDateString());

        $rangeStart = $start->isAfter($yearStart) ? $start : $yearStart;
        $rangeEnd = $end->isBefore($yearEnd) ? $end : $yearEnd;

        if ($rangeStart->isAfter($rangeEnd)) {
            return [];
        }

        $days = [];
        $cursor = $rangeStart;
        while (! $cursor->isAfter($rangeEnd)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
