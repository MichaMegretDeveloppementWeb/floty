<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Strategy for filling the gap left by deleting a VFC from history.
 *
 * - `ExtendPrevious`: the preceding VFC absorbs the deleted range (`effective_to` extended).
 * - `ExtendNext`: the following VFC absorbs the deleted range (`effective_from` pulled back).
 */
#[TypeScript]
enum FiscalCharacteristicsExtensionStrategy: string
{
    case ExtendPrevious = 'extend_previous';
    case ExtendNext = 'extend_next';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::ExtendPrevious => 'Étendre la version précédente',
            self::ExtendNext => 'Étendre la version suivante',
        };
    }
}
