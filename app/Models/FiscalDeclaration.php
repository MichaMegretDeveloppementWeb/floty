<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use Database\Factories\FiscalDeclarationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Annual fiscal declaration for a `(company, fiscal_year)` pair (ADR-0015 § 5.1 rev. 1.1).
 *
 * Several historic declarations may coexist via the obsolescence chain
 * (`is_obsolete` + `superseded_by_id`). The active declaration is the one with
 * `is_obsolete = false`; uniqueness is enforced by the repository, not by SQL.
 *
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year
 * @property string|null $reference
 * @property FiscalDeclarationStatus $status
 * @property Carbon|null $generated_at
 * @property string|null $generated_pdf_path
 * @property string|null $generated_pdf_hash
 * @property array<string, mixed>|null $generated_snapshot_payload
 * @property bool $is_obsolete
 * @property Carbon|null $obsolete_at
 * @property array<int, array<string, mixed>>|null $obsolete_reasons
 * @property string|null $defer_reason
 * @property int|null $superseded_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Company $company
 * @property-read FiscalDeclaration|null $supersededBy
 * @property-read Collection<int, FiscalDeclaration> $obsoletes
 */
#[Fillable([
    'company_id',
    'fiscal_year',
    'reference',
    'status',
    'generated_at',
    'generated_pdf_path',
    'generated_pdf_hash',
    'generated_snapshot_payload',
    'is_obsolete',
    'obsolete_at',
    'obsolete_reasons',
    'defer_reason',
    'superseded_by_id',
])]
final class FiscalDeclaration extends Model
{
    /** @use HasFactory<FiscalDeclarationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'fiscal_declarations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'status' => FiscalDeclarationStatus::class,
            'generated_at' => 'datetime',
            'generated_snapshot_payload' => 'array',
            'is_obsolete' => 'boolean',
            'obsolete_at' => 'datetime',
            'obsolete_reasons' => 'array',
        ];
    }

    /**
     * Owning company.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Successor declaration (regenerated) that makes this one obsolete.
     * Null if still active or obsolete but not yet regenerated.
     *
     * @return BelongsTo<FiscalDeclaration, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(FiscalDeclaration::class, 'superseded_by_id');
    }

    /**
     * Historic declarations replaced by this one. Inverse of `supersededBy`.
     *
     * @return HasMany<FiscalDeclaration, $this>
     */
    public function obsoletes(): HasMany
    {
        return $this->hasMany(FiscalDeclaration::class, 'superseded_by_id');
    }

    /**
     * Single predecessor declaration replaced by this one (HasOne variant of
     * `obsoletes()`; the chain is strictly linear). Convenient for eager loading.
     *
     * @return HasOne<FiscalDeclaration, $this>
     */
    public function supersedes(): HasOne
    {
        return $this->hasOne(FiscalDeclaration::class, 'superseded_by_id');
    }

    /**
     * Review decisions matched on `(company_id, fiscal_year)`. Not an Eloquent
     * relation (composite key not supported natively); returns a filtered Builder
     * for manual eager loading by services.
     *
     * @return Builder<FiscalReviewDecision>
     */
    public function relatedDecisionsQuery(): Builder
    {
        return FiscalReviewDecision::query()
            ->where('company_id', $this->company_id)
            ->where('fiscal_year', $this->fiscal_year);
    }
}
