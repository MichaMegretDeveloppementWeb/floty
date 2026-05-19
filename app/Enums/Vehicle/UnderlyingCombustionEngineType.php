<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Underlying combustion engine type for hybrids
 * (see {@see EnergySource::requiresUnderlyingCombustionEngine()}).
 * `NotApplicable` covers hybrid energies without a classic thermal engine.
 */
enum UnderlyingCombustionEngineType: string
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case NotApplicable = 'not_applicable';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Gasoline => 'Essence',
            self::Diesel => 'Diesel',
            self::NotApplicable => 'Sans objet',
        };
    }
}
