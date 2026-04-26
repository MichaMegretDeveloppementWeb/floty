<?php

namespace App\Data\User\Fiscal;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
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
}
