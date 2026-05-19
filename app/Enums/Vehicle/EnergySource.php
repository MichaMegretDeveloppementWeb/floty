<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Vehicle energy source (registration field P.3).
 * Hybrids and similar variants require {@see UnderlyingCombustionEngineType}.
 */
enum EnergySource: string
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case Electric = 'electric';
    case Hydrogen = 'hydrogen';
    case PluginHybrid = 'plugin_hybrid';
    case NonPluginHybrid = 'non_plugin_hybrid';
    case Lpg = 'lpg';
    case Cng = 'cng';
    case E85 = 'e85';
    case ElectricHydrogen = 'electric_hydrogen';

    /**
     * Whether this source implies an underlying combustion engine
     * (then {@see UnderlyingCombustionEngineType} must be set on the same VFC).
     */
    public function requiresUnderlyingCombustionEngine(): bool
    {
        return match ($this) {
            self::PluginHybrid,
            self::NonPluginHybrid,
            self::ElectricHydrogen => true,
            default => false,
        };
    }

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Gasoline => 'Essence',
            self::Diesel => 'Diesel',
            self::Electric => 'Électrique',
            self::Hydrogen => 'Hydrogène',
            self::PluginHybrid => 'Hybride rechargeable',
            self::NonPluginHybrid => 'Hybride non rechargeable',
            self::Lpg => 'GPL',
            self::Cng => 'Gaz naturel (GNV)',
            self::E85 => 'Superéthanol E85',
            self::ElectricHydrogen => 'Électrique + hydrogène',
        };
    }
}
