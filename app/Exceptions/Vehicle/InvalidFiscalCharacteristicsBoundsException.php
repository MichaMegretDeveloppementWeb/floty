<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * Les bornes (`effective_from` / `effective_to`) soumises par
 * l'utilisateur sont invalides à elles seules - sans considérer
 * l'historique du véhicule.
 *
 * Cas couverts :
 *   - `effective_from` postérieur à `effective_to`
 *   - tentative de transformer la VFC courante (effective_to=null)
 *     en VFC bornée, ou inversement (créerait deux courantes)
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

    public static function newRangeStrictlyInsideExisting(string $existingFrom, ?string $existingTo): self
    {
        $existingPeriod = $existingTo === null
            ? "depuis le {$existingFrom}"
            : "du {$existingFrom} au {$existingTo}";

        return new self(
            technicalMessage: "New range is strictly contained inside existing version ({$existingFrom} → ".($existingTo ?? 'null').').',
            userMessage: "Impossible d'insérer la nouvelle plage : elle est entièrement contenue dans la version {$existingPeriod}. Modifiez ou raccourcissez d'abord cette version depuis l'historique avant d'en ajouter une à l'intérieur.",
        );
    }
}
