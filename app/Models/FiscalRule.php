<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FiscalRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Minimal index of a fiscal rule (ADR-0022 v1.4). PHP rule classes are the single
 * source of truth; this table only carries the bridge from DB id to PHP class.
 * All metadata (name, description, legal basis, etc.) lives in the PHP classes
 * and is read via the registry. No versioning column (ADR-0009).
 *
 * @property int $id
 * @property string $rule_code
 * @property int $fiscal_year
 * @property string $code_reference Chemin de la classe PHP portant la règle (ex. `app/Fiscal/Year2024/Transversal/R2024_002_DailyProrata.php`)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'rule_code',
    'fiscal_year',
    'code_reference',
])]
final class FiscalRule extends Model
{
    /** @use HasFactory<FiscalRuleFactory> */
    use HasFactory;
}
