<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivot\DriverCompany;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Driver. May be attached to several companies over time via the
 * `driver_company` pivot (`joined_at`, `left_at`).
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read string $initials
 * @property-read int|null $active_companies_count Computed via withCount.
 * @property-read int|null $contracts_count Computed via withCount('contracts').
 */
#[Fillable([
    'first_name',
    'last_name',
])]
final class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Full name (first + last, trimmed).
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->first_name.' '.$this->last_name),
        )->shouldCache();
    }

    /**
     * Two-letter uppercase initials (first letter of first + last name).
     *
     * @return Attribute<string, never>
     */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn (): string => mb_strtoupper(
                mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1),
            ),
        )->shouldCache();
    }

    /**
     * Companies this driver has been or is attached to, with join/leave dates.
     *
     * @return BelongsToMany<Company, $this, DriverCompany, 'pivot'>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'driver_company')
            ->using(DriverCompany::class)
            ->withPivot(['id', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Contracts where this driver is listed (N:N via `contract_drivers`).
     *
     * @return BelongsToMany<Contract, $this>
     */
    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_drivers')
            ->withTimestamps();
    }

    /**
     * Whether the driver has a single membership in `$company` that covers `[start, end]`.
     */
    public function isActiveInCompanyDuring(Company $company, Carbon $start, Carbon $end): bool
    {
        return $this->companies()
            ->wherePivot('company_id', $company->id)
            ->wherePivot('joined_at', '<=', $start->toDateString())
            ->where(function ($query) use ($end): void {
                $query->whereNull('driver_company.left_at')
                    ->orWhere('driver_company.left_at', '>=', $end->toDateString());
            })
            ->exists();
    }

    /**
     * Whether the driver currently has at least one active membership (`left_at` null).
     */
    public function hasAnyActiveMembership(): bool
    {
        return $this->companies()
            ->wherePivotNull('left_at')
            ->exists();
    }
}
