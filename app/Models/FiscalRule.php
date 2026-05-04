<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Fiscal\RuleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Métadonnée d'une règle fiscale - index consultable peuplé par seeders
 * uniquement (ADR-0002).
 *
 * Cf. 02-schema-fiscal.md § 1 + ADR-0009.
 *
 * **Aucune colonne de version** (ADR-0009) : si une règle est erronée, on
 * corrige directement sa classe PHP, le `rule_code` en base reste stable.
 * L'historique des corrections vit dans git + sections « Révisions » des
 * `taxes-rules/{year}.md`.
 *
 * **Jamais de suppression** : une règle désactivée passe `is_active = false`
 * pour rester référencée par les snapshots historiques.
 *
 * @property int $id
 * @property string $rule_code
 * @property string $name
 * @property string $description
 * @property int $fiscal_year
 * @property RuleType $rule_type
 * @property array<int, string> $taxes_concerned
 * @property Carbon $applicability_start
 * @property Carbon|null $applicability_end
 * @property array<int, string>|null $vehicle_characteristics_consumed
 * @property array<int, string>|null $vehicle_characteristics_produced
 * @property array<int, array<string, mixed>> $legal_basis
 * @property string $code_reference
 * @property int $display_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'rule_code',
    'name',
    'description',
    'fiscal_year',
    'rule_type',
    'taxes_concerned',
    'applicability_start',
    'applicability_end',
    'vehicle_characteristics_consumed',
    'vehicle_characteristics_produced',
    'legal_basis',
    'code_reference',
    'display_order',
    'is_active',
])]
final class FiscalRule extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rule_type' => RuleType::class,
            'taxes_concerned' => 'array',
            'applicability_start' => 'date',
            'applicability_end' => 'date',
            'vehicle_characteristics_consumed' => 'array',
            'vehicle_characteristics_produced' => 'array',
            'legal_basis' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
