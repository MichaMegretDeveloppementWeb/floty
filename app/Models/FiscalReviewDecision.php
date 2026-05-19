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
 * Human review decision per LCD risk cluster (ADR-0015 § 5.2).
 *
 * Identified by `(company_id, fiscal_year, cluster_fingerprint)` so that
 * decisions persist independently of declarations: when regenerating an
 * obsolete declaration, decisions whose fingerprint is unchanged are re-used.
 *
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year
 * @property RiskCode $risk_code
 * @property string $cluster_fingerprint
 * @property ReviewDecisionType $decision
 * @property string|null $justification
 * @property array<int>|null $excluded_contract_ids
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
    'excluded_contract_ids',
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
            'excluded_contract_ids' => 'array',
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
