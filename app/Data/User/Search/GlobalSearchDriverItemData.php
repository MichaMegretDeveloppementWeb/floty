<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Driver item returned by the global search palette.
 *
 *  - `label`: full name.
 *  - `sublabel`: concatenated active companies, or null when none.
 *  - `href`: absolute URL to the driver page.
 */
#[TypeScript]
final class GlobalSearchDriverItemData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $sublabel,
        public string $href,
    ) {}
}
