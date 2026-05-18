<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RentalDiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Réduction commerciale appliquée sur le loyer d'une entreprise pour
 * une période donnée, sur un sous-ensemble de véhicules (ou tous si la
 * relation `vehicles` est vide · décision applicative lue par
 * `DiscountResolver`).
 *
 * **Pourcentage stocké en basis points** (1050 bp = 10,50 %) · cohérent
 * avec la doctrine projet « entiers partout ».
 *
 * **Soft deletes** · une réduction supprimée préserve son ID pour
 * référence depuis les invoice_lines déjà émises (audit immuable).
 *
 * **Non-chevauchement** garanti applicativement par
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
 * @property-read Collection<int, InvoiceLine> $invoiceLines
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
     * Entreprise utilisatrice bénéficiaire de la réduction.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Véhicules ciblés explicitement par la réduction. **Vide = applique
     * à tous les véhicules de l'entreprise sur la période** (décision
     * applicative lue dans le `DiscountResolver`, pas SQL).
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
     * Utilisateur ayant créé la réduction. Nullable (FK nullOnDelete).
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Lignes de facture déjà émises qui ont appliqué cette réduction
     * (snapshot immuable · les lignes restent valides même si la
     * réduction est ensuite modifiée ou soft-deletée).
     *
     * @return HasMany<InvoiceLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'applied_discount_id');
    }

    /**
     * Scope · réductions actives à une date donnée (inclusif sur les
     * 2 bornes). Convention `Y-m-d`.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveOn(Builder $query, string $date): void
    {
        $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    /**
     * Scope · réductions chevauchant une période donnée (inclusif).
     * Deux périodes `[a,b]` et `[c,d]` se chevauchent ssi `a <= d` et
     * `c <= b`.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlappingPeriod(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);
    }

    /**
     * Scope · réductions d'une entreprise donnée.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForCompany(Builder $query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }
}
