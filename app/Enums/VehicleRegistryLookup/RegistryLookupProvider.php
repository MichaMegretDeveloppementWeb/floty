<?php

declare(strict_types=1);

namespace App\Enums\VehicleRegistryLookup;

enum RegistryLookupProvider: string
{
    case Fake = 'fake';
    case AaaData = 'aaa_data';

    /**
     * Human-readable label for UI and audit logs.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fake => 'Fake (tests / dev)',
            self::AaaData => 'AAA Data',
        };
    }
}
