<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Enums\Fiscal\RuleSection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Section metadata for the "Règles de calcul" page (ADR-0022). Lets the
 * frontend render group titles and subtitles without hardcoding strings;
 * everything comes from the {@see RuleSection} enum.
 */
#[TypeScript]
final class FiscalRuleSectionData extends Data
{
    public function __construct(
        public RuleSection $value,
        public string $title,
        public string $subtitle,
    ) {}

    public static function fromEnum(RuleSection $section): self
    {
        return new self(
            value: $section,
            title: $section->title(),
            subtitle: $section->subtitle(),
        );
    }
}
