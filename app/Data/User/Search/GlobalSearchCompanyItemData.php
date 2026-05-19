<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Company item returned by the global search palette.
 *
 *  - `label`: legal name.
 *  - `sublabel`: SIREN when set, otherwise city or null.
 *  - `href`: absolute URL to the company page.
 */
#[TypeScript]
final class GlobalSearchCompanyItemData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $sublabel,
        public string $href,
    ) {}
}
