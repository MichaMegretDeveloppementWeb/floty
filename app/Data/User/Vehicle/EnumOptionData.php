<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Value/label pair for enum-backed `<SelectInput>` fields.
 */
#[TypeScript]
final class EnumOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
