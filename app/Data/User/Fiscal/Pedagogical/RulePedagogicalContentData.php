<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal\Pedagogical;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Contenu pédagogique d'une règle fiscale projeté vers le front
 * (Phase 13 D5.12 · ADR-0022 finalisée v1.2). Miroir du VO PHP
 * {@see App\Fiscal\ValueObjects\RulePedagogicalContent}.
 */
#[TypeScript]
final class RulePedagogicalContentData extends Data
{
    public function __construct(
        public RuleTab $tab,
        public RuleSection $section,
        public string $title,
        public string $pitch,
        public ?string $body = null,
        public ?string $appliesWhen = null,
        public ?string $effect = null,
        public ?ProgressiveBracketsTableData $progressiveBrackets = null,
        public ?FlatBracketsTableData $flatBrackets = null,
        public ?string $example = null,
    ) {}
}
