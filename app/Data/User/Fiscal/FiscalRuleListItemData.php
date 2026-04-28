<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Models\FiscalRule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une règle fiscale telle que présentée dans la page « Règles de
 * calcul » (User/FiscalRules/Index). Le contenu pédagogique enrichi
 * est associé côté front via `ruleCode` (cf. `fiscalRulesContent.ts`).
 *
 * `legalBasis` est conservé en `array<string, mixed>` car la structure
 * varie selon le type (CIBS / BOFIP / NOTICE) — un futur DTO
 * polymorphe pourra le typer plus finement.
 */
#[TypeScript]
final class FiscalRuleListItemData extends Data
{
    /**
     * @param  list<TaxType>  $taxesConcerned
     * @param  list<array<string, mixed>>  $legalBasis
     */
    public function __construct(
        public int $id,
        public string $ruleCode,
        public string $name,
        public string $description,
        public RuleType $ruleType,
        public array $taxesConcerned,
        public array $legalBasis,
        public bool $isActive,
    ) {}

    public static function fromModel(FiscalRule $rule): self
    {
        return new self(
            id: $rule->id,
            ruleCode: $rule->rule_code,
            name: $rule->name,
            description: $rule->description,
            ruleType: $rule->rule_type,
            taxesConcerned: $rule->taxes_concerned,
            legalBasis: $rule->legal_basis,
            isActive: $rule->is_active,
        );
    }
}
