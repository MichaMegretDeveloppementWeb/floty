<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\RentalDiscountObserver;
use Database\Factories\RentalDiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Commercial discount applied to a company's rent for a given period, on a
 * subset of vehicles (or all vehicles when the `vehicles` relation is empty,
 * resolved by `DiscountResolver`).
 *
 * Percentage stored in basis points (1050 bp = 10.50 %), in line with the
 * project's "integers everywhere" doctrine. Soft-deleted to preserve the id
 * for already-issued invoice lines. Non-overlap is enforced applicatively by
 * {@see App\Services\RentalDiscount\RentalDiscountConflictService}.
 *
 * @property int $id
 * @property int $company_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $discount_basis_points
 * @property string|null $label
 * @property string|null $notes
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Company $company
 * @property-read Collection<int, Vehicle> $vehicles
 * @property-read User|null $createdBy
 */
#[Fillable([
    'company_id',
    'start_date',
    'end_date',
    'discount_basis_points',
    'label',
    'notes',
    'created_by_user_id',
])]
#[ObservedBy([RentalDiscountObserver::class])]
final class RentalDiscount extends Model
{
    /** @use HasFactory<RentalDiscountFactory> */
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
            'discount_basis_points' => 'integer',
        ];
    }

    /**
     * Beneficiary user company.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Vehicles explicitly targeted by the discount. Empty means "applies to all
     * the company's vehicles on the period" (resolved in `DiscountResolver`, not SQL).
     *
     * @return BelongsToMany<Vehicle, $this>
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(
            Vehicle::class,
            'rental_discount_vehicles',
            'rental_discount_id',
            'vehicle_id',
        );
    }

    /**
     * User who created the discount (nullable via `nullOnDelete`).
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope: discounts active on the given `Y-m-d` date (inclusive on both bounds).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveOn(Builder $query, string $date): void
    {
        $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    /**
     * Scope: discounts overlapping the given period (inclusive).
     * Two ranges `[a,b]` and `[c,d]` overlap iff `a <= d` and `c <= b`.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlappingPeriod(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);
    }

    /**
     * Scope: discounts belonging to a given company.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForCompany(Builder $query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }
}
