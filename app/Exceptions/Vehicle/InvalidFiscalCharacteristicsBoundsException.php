<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;
use Carbon\CarbonImmutable;

/**
 * Submitted VFC bounds (`effective_from` / `effective_to`) are invalid in isolation.
 *
 * Covers: end before start, current↔bounded transformation attempts, and strict-containment
 * inside an existing version.
 */
final class InvalidFiscalCharacteristicsBoundsException extends BaseAppException
{
    public static function endBeforeStart(): self
    {
        return new self(
            technicalMessage: 'effective_from is after effective_to.',
            userMessage: 'La date de début doit être antérieure ou égale à la date de fin.',
        );
    }

    public static function cannotTransformCurrentToBounded(): self
    {
        return new self(
            technicalMessage: 'Cannot transform the current VFC (effective_to=null) into a bounded historic version.',
            userMessage: 'Impossible de transformer la version courante en version bornée. Utilisez « Nouvelle version » depuis le formulaire d\'édition véhicule pour clôturer la version courante.',
        );
    }

    public static function cannotTransformHistoricToCurrent(): self
    {
        return new self(
            technicalMessage: 'Cannot transform a historic VFC into the current version (would create two current versions).',
            userMessage: 'Impossible de transformer cette version historique en version courante : le véhicule a déjà une version courante.',
        );
    }

    public static function noPreviousVersionToExtend(): self
    {
        return new self(
            technicalMessage: 'Cannot extend the previous version: this is the first version of the vehicle.',
            userMessage: 'Impossible d\'étendre la version précédente : il n\'y a pas de version antérieure à celle-ci. Choisissez plutôt « Étendre la version suivante ».',
        );
    }

    public static function noNextVersionToExtend(): self
    {
        return new self(
            technicalMessage: 'Cannot extend the next version: this is the most recent version of the vehicle.',
            userMessage: 'Impossible d\'étendre la version suivante : il n\'y a pas de version postérieure à celle-ci. Choisissez plutôt « Étendre la version précédente ».',
        );
    }

    public static function effectiveFromCollidesExisting(string $date): self
    {
        return new self(
            technicalMessage: "Another VFC already starts at {$date}; cannot create a new one with the same effective_from.",
            userMessage: "Une version fiscale commence déjà au {$date}. Modifiez la version existante depuis l'historique ou choisissez une autre date de début.",
        );
    }

    public static function newRangeStrictlyInsideExisting(string $existingFrom, ?string $existingTo, string $newFrom): self
    {
        $existingFromFr = self::formatFr($existingFrom);

        // Current branch (existingTo === null): guide the user to drop the new end date
        // so the existing current VFC gets auto-closed at newFrom - 1 by the ImpactComputer.
        // Bounded branch: keep the generic wording since the only fix is to edit the existing
        // version first.
        if ($existingTo === null) {
            $newFromMinus1Fr = self::formatFr(self::previousDay($newFrom));
            $userMessage = "Cette plage est entièrement contenue dans la version courante (commencée le {$existingFromFr}). "
                ."Pour l'insérer en tant que nouvelle version courante, retirez la date de fin : "
                ."la version actuelle sera automatiquement clôturée au {$newFromMinus1Fr}.";
        } else {
            $existingToFr = self::formatFr($existingTo);
            $userMessage = "Cette plage est entièrement contenue dans la version du {$existingFromFr} au {$existingToFr}. "
                ."Modifiez ou raccourcissez d'abord cette version depuis l'historique avant d'en ajouter une à l'intérieur.";
        }

        return new self(
            technicalMessage: "New range is strictly contained inside existing version ({$existingFrom} → ".($existingTo ?? 'null').').',
            userMessage: $userMessage,
        );
    }

    private static function formatFr(string $iso): string
    {
        return CarbonImmutable::parse($iso)->format('d/m/Y');
    }

    private static function previousDay(string $iso): string
    {
        return CarbonImmutable::parse($iso)->subDay()->toDateString();
    }
}
