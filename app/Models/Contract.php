<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Contract\ContractType;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Observers\ContractObserver;
use App\Services\Contract\ContractQueryService;
use App\Support\Date\IsoWeeks;
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
 * Vehicle x company rental contract over an inclusive `[start_date, end_date]` range.
 * Pivot entity of the fiscal domain (ADR-0014).
 *
 * Invariants enforced in DB:
 * - `end_date >= start_date` (SQL CHECK).
 * - No two active contracts overlap on the same vehicle
 *   (`contracts_no_overlap_*` MySQL triggers).
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
     * Rented vehicle.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Renting company.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Drivers listed on this contract (0, 1 or many), via the egalitarian
     * `contract_drivers` pivot (no primary/secondary distinction). Optional at
     * creation; can be completed later.
     *
     * @return BelongsToMany<Driver, $this>
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'contract_drivers')
            ->withTimestamps();
    }

    /**
     * Derives the `contract_type` label from a `[start, end]` range
     * (BOFiP § 180-190 convention): `Lcd` if duration ≤ 30 days or if the
     * range covers exactly one calendar month, `Lld` otherwise.
     *
     * Pure static helper; reused by Store / Update / BulkCreate actions.
     *
     * Note: this derivation is distinct from the annual fiscal qualification
     * implemented by {@see R2024_021_ShortTermRental::isShortTermRental()}.
     * The persisted `contract_type` is an indicative label; the real fiscal
     * qualification is computed by the year-specific rule at pipeline time.
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
     * Expands the contract into ISO `Y-m-d` strings, clipped to the given year.
     * Both contract bounds are included. Reused by fiscal rules (R-2024-002, R-2024-021,
     * R-2024-008) and by {@see ContractQueryService}.
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

    /**
     * Counts the contract days falling in the given year (same clipping as
     * {@see expandToDaysInYear}). Performance variant: arithmetic diff without
     * materializing the 365-string array; must always equal
     * `count($this->expandToDaysInYear($year))` (proven by
     * `ContractCountVsExpandEquivalenceTest`).
     */
    public function countDaysInYear(int $year): int
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $start = $this->start_date->toImmutable();
        $end = $this->end_date->toImmutable();

        $rangeStart = $start->isAfter($yearStart) ? $start : $yearStart;
        $rangeEnd = $end->isBefore($yearEnd) ? $end : $yearEnd;

        if ($rangeStart->isAfter($rangeEnd)) {
            return 0;
        }

        // `diffInDays` excludes the end bound: +1 to match the "bounds included"
        // semantics of `expandToDaysInYear`.
        return (int) $rangeStart->diffInDays($rangeEnd) + 1;
    }

    /**
     * YTD variant of {@see countDaysInYear} with an extra date cap; counts the
     * contract days in `[Jan 1st $year, min(Dec 31st $year, $upToDate)]`.
     * Equivalent to `countDaysInYear` when `$upToDate >= Dec 31st $year`.
     *
     * Must equal `count(array_filter(expandToDaysInYear($year), fn($d) => $d <= $upToDate))`
     * (proven by the equivalence test in `ContractCountVsExpandEquivalenceTest`).
     */
    public function countDaysInYearUpTo(int $year, string $upToDate): int
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);
        $upToDateImmutable = CarbonImmutable::createFromFormat('Y-m-d', $upToDate);
        $effectiveEnd = $upToDateImmutable->isBefore($yearEnd) ? $upToDateImmutable : $yearEnd;

        $start = $this->start_date->toImmutable();
        $end = $this->end_date->toImmutable();

        $rangeStart = $start->isAfter($yearStart) ? $start : $yearStart;
        $rangeEnd = $end->isBefore($effectiveEnd) ? $end : $effectiveEnd;

        if ($rangeStart->isAfter($rangeEnd)) {
            return 0;
        }

        return (int) $rangeStart->diffInDays($rangeEnd) + 1;
    }

    /**
     * Distribution of contract days per heatmap cell of the given year.
     * Keys are cell indices `1..53`; values are the contract day counts.
     * Both contract bounds are clipped to `[Jan 1st $year, Dec 31st $year]`.
     *
     * Performance variant: arithmetic, week-by-week (at most 53 iterations).
     * Equivalent to `expandToDaysInYear → groupBy(cell)` (proven by
     * `ContractDaysByWeekVsExpandEquivalenceTest`).
     *
     * @return array<int, int>
     */
    public function daysByWeekInYear(int $year): array
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

        // Index by CELL position in the heatmap year (1..53) rather than ISO week
        // number, so 1-3 trailing days that ISO-belong to week 1 of Y+1 land in the
        // last cell of year Y instead of colliding with cell 1.
        $origin = IsoWeeks::cellOriginForYear($year);

        $byCell = [];
        $cursor = $rangeStart;
        while (! $cursor->isAfter($rangeEnd)) {
            // Remaining days until the current ISO week ends (Sunday).
            // `dayOfWeekIso`: 1=Monday .. 7=Sunday.
            $daysToSunday = 7 - (int) $cursor->dayOfWeekIso;
            $weekEndCandidate = $cursor->addDays($daysToSunday);
            $segmentEnd = $weekEndCandidate->isAfter($rangeEnd) ? $rangeEnd : $weekEndCandidate;

            // Cell index in the heatmap year (1..53).
            $daysSinceOrigin = (int) $origin->diffInDays($cursor);
            $cellIdx = (int) floor($daysSinceOrigin / 7) + 1;

            $daysInThisWeek = (int) $cursor->diffInDays($segmentEnd) + 1;
            $byCell[$cellIdx] = ($byCell[$cellIdx] ?? 0) + $daysInThisWeek;

            $cursor = $segmentEnd->addDay();
        }

        return $byCell;
    }
}
