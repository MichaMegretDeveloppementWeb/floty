<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivot\DriverCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Conducteur, peut être rattaché à plusieurs entreprises au cours du temps
 * via la pivot `driver_company` (joined_at, left_at).
 *
 * Cf. Phase 06 V1.2 (refonte N:N depuis le schéma 1:1 initial).
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read string $initials
 */
#[Fillable([
    'first_name',
    'last_name',
])]
final class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->first_name.' '.$this->last_name),
        )->shouldCache();
    }

    /**
     * Initiales du prénom + nom (2 lettres en majuscules).
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
     * Entreprises auxquelles ce conducteur a été rattaché (actuellement
     * ou dans le passé) avec dates d'entrée et de sortie.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'driver_company')
            ->using(DriverCompany::class)
            ->withPivot(['id', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Contrats où ce conducteur est désigné (entité pivot post ADR-0014).
     *
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Le conducteur est-il actif dans cette entreprise pendant toute la
     * période [start, end] ? (un membership unique doit couvrir la période)
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
     * Le conducteur a-t-il actuellement au moins un membership actif
     * (left_at NULL) dans une entreprise ?
     */
    public function hasAnyActiveMembership(): bool
    {
        return $this->companies()
            ->wherePivotNull('left_at')
            ->exists();
    }
}
