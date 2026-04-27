<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Declaration\DeclarationStatus;
use App\Enums\Declaration\InvalidationReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Déclaration fiscale annuelle d'une company.
 *
 * Cf. 02-schema-fiscal.md § 2.
 *
 * **Pas de soft delete** : donnée fiscale persistante. L'invalidation
 * est un drapeau orthogonal au cycle de vie — une déclaration marquée
 * invalidée reste visible et peut être régénérée via un nouveau
 * {@see DeclarationPdf}.
 *
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year
 * @property DeclarationStatus $status
 * @property Carbon $status_changed_at
 * @property int|null $status_changed_by
 * @property int|null $total_co2_tax
 * @property int|null $total_pollutant_tax
 * @property int|null $total_tax_all
 * @property Carbon|null $last_calculated_at
 * @property bool $is_invalidated
 * @property Carbon|null $invalidated_at
 * @property InvalidationReason|null $invalidation_reason
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'fiscal_year',
    'status',
    'status_changed_at',
    'status_changed_by',
    'total_co2_tax',
    'total_pollutant_tax',
    'total_tax_all',
    'last_calculated_at',
    'is_invalidated',
    'invalidated_at',
    'invalidation_reason',
    'notes',
])]
class Declaration extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeclarationStatus::class,
            'invalidation_reason' => InvalidationReason::class,
            'status_changed_at' => 'datetime',
            'last_calculated_at' => 'datetime',
            'invalidated_at' => 'datetime',
            'is_invalidated' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Dernier utilisateur à avoir modifié le statut (nullable : setNull
     * si l'utilisateur a été supprimé physiquement).
     *
     * @return BelongsTo<User, $this>
     */
    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    /**
     * Historique complet des PDF générés pour cette déclaration.
     *
     * @return HasMany<DeclarationPdf, $this>
     */
    public function pdfs(): HasMany
    {
        return $this->hasMany(DeclarationPdf::class);
    }
}
