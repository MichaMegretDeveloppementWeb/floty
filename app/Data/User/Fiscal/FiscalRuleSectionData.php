<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Enums\Fiscal\RuleSection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Métadonnée d'une section de la page « Règles de calcul » (Phase 13
 * D5.12 · ADR-0022 finalisée v1.2). Sert au front à rendre les titres
 * de groupe + sous-titres sans rien hardcoder en TS · tout vient des
 * enums PHP {@see RuleSection}.
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
