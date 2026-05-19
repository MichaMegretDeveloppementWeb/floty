<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Fiscal\ValueObjects\AppliedExemption;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Presentation DTO for an applied exemption: the `(reason, ruleCode)`
 * pair exposed in the "Exonérations applicables" panels of the
 * Vehicle Show and Contract Show pages.
 *
 * `reason` is the human-readable label; `ruleCode` (R-YYYY-XXX) lets
 * the UI open the rule's detail card.
 */
#[TypeScript]
final class AppliedExemptionData extends Data
{
    public function __construct(
        public string $reason,
        public string $ruleCode,
    ) {}

    public static function fromValueObject(AppliedExemption $exemption): self
    {
        return new self(
            reason: $exemption->reason,
            ruleCode: $exemption->ruleCode,
        );
    }
}
