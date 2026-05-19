<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Pair `(reason, ruleCode)` exposed in the pipeline result for the
 * user-facing "Exonérations applicables" panel.
 *
 * Lets each textual motive be traced to the rule that produced it so
 * the UI can open the matching R-YYYY-XXX rule sheet.
 */
final readonly class AppliedExemption
{
    public function __construct(
        public string $reason,
        public string $ruleCode,
    ) {}
}
