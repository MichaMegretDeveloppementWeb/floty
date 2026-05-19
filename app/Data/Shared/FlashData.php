<?php

declare(strict_types=1);

namespace App\Data\Shared;

use App\Support\Toasts\ToastDispatcher;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flash messages exposed as an Inertia shared prop.
 *
 * `toasts` accumulates multiple entries per request (a single response
 * may stack several through {@see ToastDispatcher}).
 * Each entry carries a unique id so the frontend can deduplicate when
 * Inertia restores `flash.toasts` from history.state on back-button.
 *
 * The legacy scalar channels (success / error / warning / info) remain
 * for back-compat with controllers calling `back()->with('toast-...')`;
 * the Inertia middleware converts them into `toasts` entries on share.
 *
 * @property array<int, ToastEntryData> $toasts
 */
#[TypeScript]
final class FlashData extends Data
{
    /**
     * @param  array<int, ToastEntryData>  $toasts
     */
    public function __construct(
        public ?string $success,
        public ?string $error,
        public ?string $warning,
        public ?string $info,
        #[DataCollectionOf(ToastEntryData::class)]
        public array $toasts,
    ) {}
}
