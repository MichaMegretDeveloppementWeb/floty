<?php

declare(strict_types=1);

namespace App\Data\Shared;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Typed entry of a toast served through Inertia shared props.
 *
 * The `id` (UUID v4 or deterministic hash) allows the frontend to
 * deduplicate when Inertia restores `flash.toasts` from history.state
 * after a back-button navigation.
 *
 * @property string $id
 * @property string $tone success | error | warning | info
 * @property string $message
 */
#[TypeScript]
final class ToastEntryData extends Data
{
    public function __construct(
        public string $id,
        public string $tone,
        public string $message,
    ) {}
}
