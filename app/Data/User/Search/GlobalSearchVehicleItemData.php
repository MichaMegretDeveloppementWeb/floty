<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vehicle item returned by the global search palette.
 *
 *  - `label`: "Renault Clio · AB-123-CD" (brand + model + plate).
 *  - `sublabel`: vehicle state ("Actif" / "Sorti le 12/03/2025").
 *  - `href`: absolute URL to the vehicle page.
 */
#[TypeScript]
final class GlobalSearchVehicleItemData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $sublabel,
        public string $href,
    ) {}
}
