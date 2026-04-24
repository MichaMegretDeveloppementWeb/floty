<?php

namespace App\Enums\Unavailability;

/**
 * Type d'indisponibilité d'un véhicule.
 *
 * Seul le type `Pound` (fourrière) a un **impact fiscal** — il réduit
 * le numérateur du prorata journalier (R-2024-008). La colonne
 * `unavailabilities.has_fiscal_impact` dénormalise cette information
 * pour le requêtage rapide ; un CHECK constraint en base garantit la
 * cohérence `has_fiscal_impact = (type = 'pound')`.
 */
enum UnavailabilityType: string
{
    case Maintenance = 'maintenance';
    case TechnicalInspection = 'technical_inspection';
    case Accident = 'accident';
    case Pound = 'pound';
    case Other = 'other';

    /**
     * Vrai ssi ce type réduit le numérateur du prorata fiscal.
     */
    public function hasFiscalImpact(): bool
    {
        return $this === self::Pound;
    }
}
