<?php

namespace App\Enums\Vehicle;

/**
 * Source d'énergie du véhicule (rubrique P.3 de la carte grise).
 *
 * Pour les hybrides et assimilés, la caractéristique
 * {@see UnderlyingCombustionEngineType} est obligatoire (invariant
 * cross-champs validé par `VehicleFiscalCharacteristicsService`).
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
     * Renvoie vrai pour les sources qui impliquent un moteur thermique
     * sous-jacent — l'implémentation fiscale doit alors renseigner
     * {@see UnderlyingCombustionEngineType} sur la même caractéristique.
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
