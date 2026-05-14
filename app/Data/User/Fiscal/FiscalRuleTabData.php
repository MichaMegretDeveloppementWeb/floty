<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Enums\Fiscal\RuleTab;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Métadonnée d'un onglet de la page « Règles de calcul » (Phase 13
 * D5.12 · ADR-0022 finalisée v1.2). Tout vient des enums PHP
 * {@see RuleTab} et {@see App\Enums\Fiscal\RuleSection} · plus rien
 * de hardcoded côté TS.
 */
#[TypeScript]
final class FiscalRuleTabData extends Data
{
    /**
     * @param  list<FiscalRuleSectionData>  $sections
     */
    public function __construct(
        public RuleTab $value,
        public string $label,
        #[DataCollectionOf(FiscalRuleSectionData::class)]
        public array $sections,
    ) {}

    public static function fromEnum(RuleTab $tab): self
    {
        return new self(
            value: $tab,
            label: $tab->label(),
            sections: array_map(
                static fn ($section): FiscalRuleSectionData => FiscalRuleSectionData::fromEnum($section),
                $tab->sectionsOrder(),
            ),
        );
    }
}
