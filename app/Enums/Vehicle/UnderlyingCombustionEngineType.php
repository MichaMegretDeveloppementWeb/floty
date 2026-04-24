<?php

namespace App\Enums\Vehicle;

/**
 * Type de moteur thermique sous-jacent — renseigné pour les véhicules
 * hybrides (cf. {@see EnergySource::requiresUnderlyingCombustionEngine()}).
 *
 * La valeur `NotApplicable` couvre les cas exotiques où l'énergie
 * primaire est hybride mais sans moteur thermique au sens classique
 * (ex. futur véhicule pile à combustible + batterie — `electric_hydrogen`).
 */
enum UnderlyingCombustionEngineType: string
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case NotApplicable = 'not_applicable';
}
