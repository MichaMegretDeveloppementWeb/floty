<?php

declare(strict_types=1);

namespace App\Rules\Vehicle;

use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejette toute saisie de pÃ©riode (crÃ©ation/Ã©dition de contrat ou
 * d'indisponibilitÃ©) sur un vÃ©hicule sorti de flotte dÃ¨s lors que la
 * pÃ©riode chevauche ou dÃ©passe `vehicles.exit_date`.
 *
 * Cf. ADR-0018 Â§ 5 (4 cas du tableau de validation pÃ©riode) :
 *
 *   | Cas                                  | Comportement                                 |
 *   |--------------------------------------|----------------------------------------------|
 *   | exit_date IS NULL                    | âœ… Toujours autorisÃ©                         |
 *   | end < exit_date (entiÃ¨rement avant)  | âœ… AutorisÃ©                                  |
 *   | start >= exit_date (entiÃ¨rement aprÃ¨s) | âŒ RejetÃ©                                    |
 *   | start < exit_date <= end (chevauche) | âŒ RejetÃ©                                    |
 *
 * La rÃ¨gle ne valide pas le champ qu'elle dÃ©core (la date est passÃ©e
 * en argument constructor, comme la pÃ©riode complÃ¨te) ; elle agit comme
 * un "implies" sur la combinaison `vehicleId` + `[start, end]`. Elle
 * appartient donc typiquement au bloc des rÃ¨gles "globales" d'un DTO
 * Spatie Data, attachÃ©e au champ `vehicleId` ou Ã  la date de fin (au
 * choix du DTO).
 *
 * Le vÃ©hicule est lookupÃ© en BDD Ã  chaque Ã©valuation. Performance : 1
 * requÃªte simple par appel ; nÃ©gligeable au regard du chemin de
 * validation oÃ¹ elle s'insÃ¨re.
 */
final class AvailableForPeriod implements ValidationRule
{
    public function __construct(
        private readonly int $vehicleId,
        private readonly CarbonImmutable $startDate,
        private readonly CarbonImmutable $endDate,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $vehicle = Vehicle::query()->find($this->vehicleId);

        // VÃ©hicule introuvable : on ne lÃ¨ve pas ici (une autre rÃ¨gle
        // `exists:vehicles,id` couvre ce cas en amont). Pas de
        // validation Ã  faire si on n'a pas de rÃ©fÃ©rence.
        if ($vehicle === null) {
            return;
        }

        // Cas 1 : vÃ©hicule jamais sorti â†’ toujours autorisÃ©.
        if ($vehicle->exit_date === null) {
            return;
        }

        $exitDate = $vehicle->exit_date->toImmutable();
        $exitFr = $exitDate->format('d/m/Y');

        // Cas 2 : pÃ©riode entiÃ¨rement avant exit_date â†’ autorisÃ©.
        if ($this->endDate->lessThan($exitDate)) {
            return;
        }

        // Cas 3 : pÃ©riode entiÃ¨rement aprÃ¨s exit_date.
        if ($this->startDate->greaterThanOrEqualTo($exitDate)) {
            $fail(sprintf(
                'VÃ©hicule retirÃ© depuis le %s. Aucune pÃ©riode n\'est autorisÃ©e Ã  partir de cette date.',
                $exitFr,
            ));

            return;
        }

        // Cas 4 : pÃ©riode chevauche exit_date (start < exit_date <= end).
        $fail(sprintf(
            'VÃ©hicule retirÃ© le %s. La pÃ©riode ne peut pas dÃ©passer cette date.',
            $exitFr,
        ));
    }
}
