<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use Database\Factories\FiscalReviewDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Décision humaine de revue fiscale par cluster (Phase 11 D1,
 * ADR-0015 § 5.2).
 *
 * Identifiée par `(company_id, fiscal_year, cluster_fingerprint)` pour
 * permettre la persistence indépendante des déclarations : à la
 * régénération d'une déclaration obsolète, les décisions dont le
 * fingerprint est inchangé sont auto-reprises.
 *
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year
 * @property RiskCode $risk_code
 * @property string $cluster_fingerprint
 * @property ReviewDecisionType $decision
 * @property string|null $justification
 * @property int $decided_by
 * @property Carbon $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read User $decidedBy
 */
#[Fillable([
    'company_id',
    'fiscal_year',
    'risk_code',
    'cluster_fingerprint',
    'decision',
    'justification',
    'decided_by',
    'decided_at',
])]
final class FiscalReviewDecision extends Model
{
    /** @use HasFactory<FiscalReviewDecisionFactory> */
    use HasFactory;

    protected $table = 'fiscal_review_decisions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'risk_code' => RiskCode::class,
            'decision' => ReviewDecisionType::class,
            'decided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
