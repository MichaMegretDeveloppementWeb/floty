<?php

declare(strict_types=1);

namespace App\Exceptions\Fiscal;

use App\Exceptions\BaseAppException;

/**
 * Erreur du moteur fiscal - données d'entrée incohérentes ou état
 * véhicule manquant pour produire un calcul.
 *
 * Référence : implementation-rules/gestion-erreurs.md.
 */
final class FiscalCalculationException extends BaseAppException
{
    public static function yearNotSupported(int $year): self
    {
        return new self(
            technicalMessage: "Fiscal year {$year} is not supported by the current calculator (cf. config/floty.php available_years).",
            userMessage: "L'année fiscale {$year} n'est pas supportée par cette version du moteur. Contactez le support si le problème persiste.",
        );
    }

    public static function negativeDays(int $days): self
    {
        return new self(
            technicalMessage: "Days assigned to company must be >= 0, got {$days}.",
            userMessage: 'Le nombre de jours fournis au calcul fiscal est invalide. Veuillez réessayer ou contacter le support.',
        );
    }

    public static function cumulInferiorToAssigned(int $cumul, int $days): self
    {
        return new self(
            technicalMessage: "Cumulative days for the (vehicle, company) pair ({$cumul}) cannot be lower than the days assigned in the current call ({$days}).",
            userMessage: 'Incohérence dans le cumul annuel du couple véhicule × entreprise. Veuillez réessayer ou contacter le support.',
        );
    }

    public static function missingFiscalCharacteristics(int $vehicleId): self
    {
        return new self(
            technicalMessage: "Vehicle #{$vehicleId} has no current fiscal characteristics (effective_to IS NULL).",
            userMessage: "Le véhicule sélectionné n'a pas de caractéristiques fiscales actives. Vérifiez sa fiche ou contactez le support.",
        );
    }

    public static function noYearsConfigured(): self
    {
        return new self(
            technicalMessage: 'No fiscal years configured (config/floty.php available_years is empty).',
            userMessage: "Aucune année fiscale n'est configurée pour cette installation. Contactez le support.",
        );
    }

    public static function invalidBracket(int $lower, int $upper): self
    {
        return new self(
            technicalMessage: "Invalid bracket range: upperInclusive ({$upper}) must be strictly greater than lowerExclusive ({$lower}).",
            userMessage: 'Erreur interne du barème fiscal. Contactez le support.',
        );
    }

    public static function negativeBracketRate(float $rate): self
    {
        return new self(
            technicalMessage: "Bracket marginal rate must be >= 0.0, got {$rate}.",
            userMessage: 'Erreur interne du barème fiscal. Contactez le support.',
        );
    }

    public static function emptyScale(): self
    {
        return new self(
            technicalMessage: 'A ProgressiveScale must contain at least one bracket.',
            userMessage: 'Erreur interne du barème fiscal. Contactez le support.',
        );
    }

    public static function scaleDiscontinuity(int $index, int $expectedLower, int $actualLower): self
    {
        return new self(
            technicalMessage: "ProgressiveScale discontinuity at bracket #{$index}: expected lowerExclusive={$expectedLower}, got {$actualLower}.",
            userMessage: 'Erreur interne du barème fiscal. Contactez le support.',
        );
    }

    public static function scaleOpenBracketNotLast(int $index): self
    {
        return new self(
            technicalMessage: "ProgressiveScale: only the last bracket may be open-ended (null upperInclusive); bracket #{$index} is open but not last.",
            userMessage: 'Erreur interne du barème fiscal. Contactez le support.',
        );
    }

    public static function pollutantTariffMissingCategory(string $category): self
    {
        return new self(
            technicalMessage: "PollutantTariff is missing tariff for category '{$category}'.",
            userMessage: 'Erreur interne du barème polluants. Contactez le support.',
        );
    }

    public static function pollutantTariffNegative(string $category, float $value): self
    {
        return new self(
            technicalMessage: "PollutantTariff for category '{$category}' must be >= 0.0, got {$value}.",
            userMessage: 'Erreur interne du barème polluants. Contactez le support.',
        );
    }
}
