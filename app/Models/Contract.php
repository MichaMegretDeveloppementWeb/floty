<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Contract\ContractType;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Observers\ContractObserver;
use App\Services\Contract\ContractQueryService;
use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Contrat de location vÃ©hicule Ã— entreprise sur une plage temporelle
 * inclusive `[start_date, end_date]`. EntitÃ© pivot du domaine fiscal
 * post-refonte (cf. ADR-0014 Â« ModÃ¨le Contract et rÃ¨gle LCD par
 * contrat individuel Â»).
 *
 * Cf. `taxes-rules/2024.md` v2.0 R-2024-021 pour la mÃ©canique
 * d'exonÃ©ration LCD et `database/migrations/2026_04_29_140000_create_contracts_table.php`
 * pour la structure DB.
 *
 * **Invariants critiques** (matÃ©rialisÃ©s en DB) :
 *   - `end_date >= start_date` (CHECK SQL)
 *   - Pas deux contrats actifs chevauchants sur le mÃªme vÃ©hicule
 *     (triggers MySQL `contracts_no_overlap_*`)
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $company_id
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
    'start_date',
    'end_date',
    'contract_reference',
    'contract_type',
    'notes',
])]
#[ObservedBy([ContractObserver::class])]
final class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    use SoftDeletes;

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
     * Conducteurs dÃ©signÃ©s sur ce contrat (0, 1 ou plusieurs). Pivot
     * pur Ã©galitaire `contract_drivers` Â· pas de notion de conducteur
     * principal/secondaire, tous les conducteurs sont Ã©quivalents.
     *
     * Optionnel Ã  la crÃ©ation (un contrat peut Ãªtre crÃ©Ã© sans conducteur
     * et complÃ©tÃ© ensuite).
     *
     * @return BelongsToMany<Driver, $this>
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'contract_drivers')
            ->withTimestamps();
    }

    /**
     * DÃ©rive le `contract_type` Ã  partir d'une plage `[start, end]`.
     *
     * Convention BOFiP Â§ 180-190 (Â« Ã©ternelle Â») :
     *   - durÃ©e â‰¤ 30 jours consÃ©cutifs â†’ `Lcd`
     *   - **OU** plage couvrant exactement un mois civil entier
     *     (1er â†’ dernier jour du mÃªme mois) â†’ `Lcd`
     *   - sinon â†’ `Lld`
     *
     * MÃ©thode statique pure : pas d'IO, pas d'Ã©tat. RÃ©utilisable cÃ´tÃ©
     * Actions (Store/Update/BulkCreate) qui posent automatiquement le
     * type avant persistance.
     *
     * **Note architecture** : cette dÃ©rivation est distincte de la
     * qualification fiscale annuelle portÃ©e par
     * {@see R2024_021_ShortTermRental::isShortTermRental()}.
     * Le `contract_type` persistÃ© est un **libellÃ© indicatif** figÃ© Ã
     * la crÃ©ation/Ã©dition ; la qualification fiscale rÃ©elle s'Ã©value
     * dans le pipeline avec la rÃ¨gle de l'annÃ©e concernÃ©e.
     */
    public static function deriveTypeFromDates(string $startDate, string $endDate): ContractType
    {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);

        $duration = (int) $start->diffInDays($end) + 1;

        if ($duration <= 30) {
            return ContractType::Lcd;
        }

        $isFullCalendarMonth = $start->day === 1
            && $end->day === $end->daysInMonth
            && $start->month === $end->month
            && $start->year === $end->year;

        if ($isFullCalendarMonth) {
            return ContractType::Lcd;
        }

        return ContractType::Lld;
    }

    /**
     * Expansion du contrat en liste de dates ISO (Y-m-d), bornÃ©e Ã
     * l'annÃ©e passÃ©e en argument. Inclut les deux bornes du contrat.
     *
     * Helper rÃ©utilisÃ© par les rÃ¨gles fiscales (R-2024-002 numÃ©rateur
     * du prorata, R-2024-021 qualification LCD per-contract,
     * R-2024-008 jours indispos rÃ©ductrices âˆ© contrats taxables) et
     * par {@see ContractQueryService}.
     *
     * @return list<string>
     */
    public function expandToDaysInYear(int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $start = $this->start_date->toImmutable();
        $end = $this->end_date->toImmutable();

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
