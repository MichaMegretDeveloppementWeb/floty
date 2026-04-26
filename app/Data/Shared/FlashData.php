<?php

namespace App\Data\Shared;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Quatre canaux de flash messages — un par variante de Toast du DS.
 * Les controllers alimentent via `->with('toast-success', '...')` et le
 * front lit `flash.success` (etc.).
 */
#[TypeScript]
final class FlashData extends Data
{
    public function __construct(
        public ?string $success,
        public ?string $error,
        public ?string $warning,
        public ?string $info,
    ) {}
}
