<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Vehicle Euro standard (registration field V.9). All Euro 5/6 sub-variants are
 * represented explicitly because the 2024 fiscal catalog handles them (R-2024-013).
 */
enum EuroStandard: string
{
    case Euro1 = 'euro_1';
    case Euro2 = 'euro_2';
    case Euro3 = 'euro_3';
    case Euro4 = 'euro_4';
    case Euro5 = 'euro_5';
    case Euro5a = 'euro_5a';
    case Euro5b = 'euro_5b';
    case Euro6 = 'euro_6';
    case Euro6b = 'euro_6b';
    case Euro6c = 'euro_6c';
    case Euro6dTemp = 'euro_6d_temp';
    case Euro6d = 'euro_6d';
    case Euro6dIsc = 'euro_6d_isc';
    case Euro6dIscFcm = 'euro_6d_isc_fcm';

    /**
     * Whether this standard is Euro 5 or above (any sub-variant). Used by R-2024-013.
     */
    public function isEuro5OrAbove(): bool
    {
        return match ($this) {
            self::Euro1, self::Euro2, self::Euro3, self::Euro4 => false,
            default => true,
        };
    }

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Euro1 => 'Euro 1',
            self::Euro2 => 'Euro 2',
            self::Euro3 => 'Euro 3',
            self::Euro4 => 'Euro 4',
            self::Euro5 => 'Euro 5',
            self::Euro5a => 'Euro 5a',
            self::Euro5b => 'Euro 5b',
            self::Euro6 => 'Euro 6',
            self::Euro6b => 'Euro 6b',
            self::Euro6c => 'Euro 6c',
            self::Euro6dTemp => 'Euro 6d-Temp',
            self::Euro6d => 'Euro 6d',
            self::Euro6dIsc => 'Euro 6d-ISC',
            self::Euro6dIscFcm => 'Euro 6d-ISC-FCM',
        };
    }
}
