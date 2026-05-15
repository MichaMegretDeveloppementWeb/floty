<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Data\User\Fiscal\Pedagogical\RulePedagogicalContentData;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\FiscalRule as FiscalRuleContract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une règle fiscale telle que présentée dans la page « Règles de
 * calcul » et dans les breakdowns véhicule/contrat (Phase 13 D5.14 ·
 * ADR-0022 finalisée v1.4).
 *
 * **Source** · construit exclusivement depuis la classe PHP de la
 * règle via {@see self::fromRule()}. La BDD `fiscal_rules` ne sert
 * plus que d'index pour récupérer l'`$id` stable, à des fins de FK
 * potentielles (non consommé côté front actuellement).
 *
 * **Granularité temporelle** · les dates `applicabilityStart` /
 * `applicabilityEnd` exposées sont **clippées à l'année consultée**.
 * `isFullYear` permet à l'UI d'afficher silencieusement le cas
 * standard et de mettre en évidence les règles partielles.
 */
#[TypeScript]
final class FiscalRuleListItemData extends Data
{
    /**
     * @param  list<TaxType>  $taxesConcerned
     * @param  list<array{type: string, article?: string, reference?: string, paragraph?: string, url?: string, consulted_at?: string}>  $legalBasis
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
     * Construit le DTO depuis la classe PHP de la règle (la source
     * de vérité unique depuis D5.13). Le paramètre `$id` provient de
     * l'index BDD `fiscal_rules`, récupéré par batch via
     * `FiscalRuleReadRepository::findIdsByCodeForYear()`.
     */
    public static function fromRule(FiscalRuleContract $rule, int $year, int $id): self
    {
        $yearStart = sprintf('%d-01-01', $year);
        $yearEnd = sprintf('%d-12-31', $year);

        $ruleStart = $rule->applicabilityStart()->toDateString();
        $ruleEnd = $rule->applicabilityEnd()?->toDateString() ?? $yearEnd;

        // Comparaisons string sur dates ISO `YYYY-MM-DD` ·
        // l'ordre lexicographique = ordre chronologique pour ce format.
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
}
