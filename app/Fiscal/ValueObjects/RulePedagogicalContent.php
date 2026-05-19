<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;

/**
 * Pedagogical content of a fiscal rule, displayed on the "Règles de
 * calcul" page (ADR-0022).
 *
 * Per ADR-0022, the pedagogical content of a rule lives in its PHP
 * class, accessed via `FiscalRule::pedagogicalContent()`. All textual
 * fields are in French, written for a fleet manager (not a lawyer).
 *
 * `progressiveBrackets` / `flatBrackets` carry the brackets formatted
 * for display (labels and rates as render-ready strings, e.g.
 * `'0 à 14 g/km'`, `'1 €'`). The purely numerical bracket structure
 * (used by the calculation engine) lives in the rule's `apply()`
 * method; both representations must stay consistent.
 */
final readonly class RulePedagogicalContent
{
    public function __construct(
        public RuleTab $tab,
        public RuleSection $section,
        /** Short title shown at the top of the card. */
        public string $title,
        /** One-sentence summary of the essence. */
        public string $pitch,
        /** Longer paragraph explaining the calculation or condition. */
        public ?string $body = null,
        /** Application condition (for exemptions and routing). */
        public ?string $appliesWhen = null,
        /** Effect on the calculation (for exemptions). */
        public ?string $effect = null,
        public ?ProgressiveBracketsTable $progressiveBrackets = null,
        public ?FlatBracketsTable $flatBrackets = null,
        /** Concrete numerical example. */
        public ?string $example = null,
    ) {}
}
