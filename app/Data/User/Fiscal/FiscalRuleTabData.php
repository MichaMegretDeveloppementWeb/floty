<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Tab metadata for the "Règles de calcul" page (ADR-0022). Sourced from
 * the {@see RuleTab} and {@see RuleSection} enums.
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
