<?php

declare(strict_types=1);

namespace App\Enums\VehicleRegistryLookup;

enum RegistryLookupDriver: string
{
    case Fake = 'fake';
    case ApiPlaque = 'api_plaque';
    case AaaData = 'aaa_data';

    /**
     * Human-readable label for UI and audit logs.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fake => 'Fake (tests / dev)',
            self::ApiPlaque => 'API Plaque (auto-ways.net via RapidAPI)',
            self::AaaData => 'AAA Data',
        };
    }
}
