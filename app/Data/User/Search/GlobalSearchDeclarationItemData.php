<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Declaration item returned by the global search palette.
 *
 * Triggered only when the query contains a year (regex `\b(20\d{2})\b`)
 * and at least one company token. The latest active version
 * (`is_obsolete = false`) is returned per `(company, year)` so each item
 * is unambiguously actionable.
 *
 *  - `label`: "ACME · Déclaration 2025".
 *  - `sublabel`: humanised status ("Générée le 12/03/2026" / "Brouillon").
 *  - `href`: absolute URL to the declaration page.
 */
#[TypeScript]
final class GlobalSearchDeclarationItemData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $sublabel,
        public string $href,
    ) {}
}
