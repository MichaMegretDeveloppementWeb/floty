<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FiscalRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Index minimal d'une règle fiscale (Phase 13 D5.14 · ADR-0022 v1.4).
 *
 * **Doctrine** · les classes PHP des règles fiscales sont la source
 * de vérité unique. Cette table ne porte plus que l'index nécessaire
 * pour relier l'id BDD à la classe PHP · toutes les métadonnées
 * (name, description, legal_basis, etc.) vivent désormais
 * exclusivement dans les classes PHP, lues via le registry.
 *
 * **Aucune colonne de version** (ADR-0009) · si une règle est erronée,
 * on corrige directement sa classe PHP, le `rule_code` reste stable.
 *
 * **Jamais de suppression** · le seeder en mode miroir supprime les
 * entrées orphelines uniquement (cf. `FiscalRulesSeeder`).
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
