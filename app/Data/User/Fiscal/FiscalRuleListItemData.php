<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Data\User\Fiscal\Pedagogical\RulePedagogicalContentData;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\FiscalRule as FiscalRuleContract;
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
        public ?RulePedagogicalContentData $pedagogicalContent,
        public bool $isActive,
        public string $applicabilityStartInYear,
        public string $applicabilityEndInYear,
        public bool $isFullYear,
    ) {}

    /**
     * Construit le DTO **directement depuis la classe PHP de la règle**
     * (Phase 13 D5.13 · ADR-0022 finalisée v1.3 « tout vient des
     * classes PHP, la BDD n'est qu'un index »).
     *
     * Le paramètre `$id` est l'identifiant stable de l'index BDD,
     * récupéré par batch via `FiscalRuleReadRepository::findIdsByCodeForYear()`.
     * Il reste exposé au DTO uniquement pour préserver la signature
     * historique · le front Floty ne l'utilise actuellement nulle part.
     */
    public static function fromRule(FiscalRuleContract $rule, int $year, int $id): self
    {
        $yearStart = sprintf('%d-01-01', $year);
        $yearEnd = sprintf('%d-12-31', $year);

        $ruleStart = $rule->applicabilityStart()->toDateString();
        $ruleEnd = $rule->applicabilityEnd()?->toDateString() ?? $yearEnd;

        $startInYear = $ruleStart > $yearStart ? $ruleStart : $yearStart;
        $endInYear = $ruleEnd < $yearEnd ? $ruleEnd : $yearEnd;

        return new self(
            id: $id,
            ruleCode: $rule->ruleCode(),
            name: $rule->name(),
            description: $rule->description(),
            ruleType: $rule->ruleType(),
            taxesConcerned: $rule->taxesConcerned(),
            legalBasis: $rule->legalBasis(),
            pedagogicalContent: RulePedagogicalContentData::fromVo($rule->pedagogicalContent()),
            isActive: $rule->isActive(),
            applicabilityStartInYear: $startInYear,
            applicabilityEndInYear: $endInYear,
            isFullYear: $startInYear === $yearStart && $endInYear === $yearEnd,
        );
    }

    public static function fromModel(FiscalRule $rule, int $year): self
    {
        $yearStart = sprintf('%d-01-01', $year);
        $yearEnd = sprintf('%d-12-31', $year);

        $ruleStart = $rule->applicability_start->toDateString();
        $ruleEnd = $rule->applicability_end?->toDateString() ?? $yearEnd;

        // Comparaisons string sur dates ISO `YYYY-MM-DD` :
        // l'ordre lexicographique est strictement = ordre chronologique
        // pour ce format. Évite la conversion vers Carbon qui ne sert à
        // rien ici (pas d'arithmétique, juste min/max).
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
            pedagogicalContent: $rule->pedagogical_content !== null
                ? RulePedagogicalContentData::from($rule->pedagogical_content)
                : null,
            isActive: $rule->is_active,
            applicabilityStartInYear: $startInYear,
            applicabilityEndInYear: $endInYear,
            isFullYear: $startInYear === $yearStart && $endInYear === $yearEnd,
        );
    }
}
