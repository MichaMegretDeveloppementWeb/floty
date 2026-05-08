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
 * varie selon le type (CIBS / BOFIP / NOTICE) - un futur DTO
 * polymorphe pourra le typer plus finement.
 *
 * **Granularité temporelle (chantier κ.7)** : les dates `applicabilityStart`
 * / `applicabilityEnd` exposées sont **clippées à l'année consultée**.
 * `isFullYear` permet à l'UI d'afficher silencieusement le cas standard
 * (toute l'année) et de mettre en évidence les règles partielles.
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
        public string $applicabilityStartInYear,
        public string $applicabilityEndInYear,
        public bool $isFullYear,
    ) {}

    public static function fromModel(FiscalRule $rule, int $year): self
    {
        $yearStart = sprintf('%d-01-01', $year);
        $yearEnd = sprintf('%d-12-31', $year);

        $ruleStart = $rule->applicability_start->toDateString();
        $ruleEnd = $rule->applicability_end?->toDateString() ?? $yearEnd;

        $startInYear = $ruleStart > $yearStart ? $ruleStart : $yearStart;
        $endInYear = $ruleEnd < $yearEnd ? $ruleEnd : $yearEnd;

        return new self(
            id: $rule->id,
            ruleCode: $rule->rule_code,
            name: $rule->name,
            description: $rule->description,
            ruleType: $rule->rule_type,
            taxesConcerned: array_values(array_map(
                static fn (string $value): TaxType => TaxType::from($value),
                $rule->taxes_concerned,
            )),
            legalBasis: $rule->legal_basis,
            isActive: $rule->is_active,
            applicabilityStartInYear: $startInYear,
            applicabilityEndInYear: $endInYear,
            isFullYear: $startInYear === $yearStart && $endInYear === $yearEnd,
        );
    }
}
