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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Déclaration fiscale annuelle pour un couple `(company, fiscal_year)`
 * (Phase 11 D1, ADR-0015 § 5.1 rev. 1.1).
 *
 * Plusieurs déclarations historiques peuvent coexister pour un même
 * couple grâce à la chaîne d'obsolescence (`is_obsolete` +
 * `superseded_by_id`). La déclaration **active** d'un couple est
 * celle dont `is_obsolete = false` ; l'unicité fonctionnelle est
 * garantie par le repository, pas par contrainte SQL.
 *
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year
 * @property FiscalDeclarationStatus $status
 * @property Carbon|null $generated_at
 * @property string|null $generated_pdf_path
 * @property string|null $generated_pdf_hash
 * @property bool $is_obsolete
 * @property Carbon|null $obsolete_at
 * @property array<int, array<string, mixed>>|null $obsolete_reasons
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
    'status',
    'generated_at',
    'generated_pdf_path',
    'generated_pdf_hash',
    'is_obsolete',
    'obsolete_at',
    'obsolete_reasons',
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
            'is_obsolete' => 'boolean',
            'obsolete_at' => 'datetime',
            'obsolete_reasons' => 'array',
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
     * Déclaration suivante (régénérée) qui rend celle-ci obsolète. Null
     * si la déclaration est encore active OU si elle est obsolète mais
     * pas encore régénérée.
     *
     * @return BelongsTo<FiscalDeclaration, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(FiscalDeclaration::class, 'superseded_by_id');
    }

    /**
     * Déclarations historiques que celle-ci remplace (rare en pratique :
     * chaîne linéaire 1 ancien → 1 nouveau). Inverse de `supersededBy`.
     *
     * @return HasMany<FiscalDeclaration, $this>
     */
    public function obsoletes(): HasMany
    {
        return $this->hasMany(FiscalDeclaration::class, 'superseded_by_id');
    }

    /**
     * Décisions de revue rattachées au couple `(company_id,
     * fiscal_year)` de cette déclaration. Pas une vraie relation
     * Eloquent (clé composite non supportée nativement) : retourne un
     * Builder filtré, à éager-loader manuellement par les services.
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
