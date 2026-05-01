<?php

declare(strict_types=1);

namespace App\Rules\Vehicle;

use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejette toute saisie de période (création/édition de contrat ou
 * d'indisponibilité) sur un véhicule sorti de flotte dès lors que la
 * période chevauche ou dépasse `vehicles.exit_date`.
 *
 * Cf. ADR-0018 § 5 (4 cas du tableau de validation période) :
 *
 *   | Cas                                  | Comportement                                 |
 *   |--------------------------------------|----------------------------------------------|
 *   | exit_date IS NULL                    | ✅ Toujours autorisé                         |
 *   | end < exit_date (entièrement avant)  | ✅ Autorisé                                  |
 *   | start >= exit_date (entièrement après) | ❌ Rejeté                                    |
 *   | start < exit_date <= end (chevauche) | ❌ Rejeté                                    |
 *
 * La règle ne valide pas le champ qu'elle décore (la date est passée
 * en argument constructor, comme la période complète) ; elle agit comme
 * un "implies" sur la combinaison `vehicleId` + `[start, end]`. Elle
 * appartient donc typiquement au bloc des règles "globales" d'un DTO
 * Spatie Data, attachée au champ `vehicleId` ou à la date de fin (au
 * choix du DTO).
 *
 * Le véhicule est lookupé en BDD à chaque évaluation. Performance : 1
 * requête simple par appel ; négligeable au regard du chemin de
 * validation où elle s'insère.
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

        // Véhicule introuvable : on ne lève pas ici (une autre règle
        // `exists:vehicles,id` couvre ce cas en amont). Pas de
        // validation à faire si on n'a pas de référence.
        if ($vehicle === null) {
            return;
        }

        // Cas 1 : véhicule jamais sorti → toujours autorisé.
        if ($vehicle->exit_date === null) {
            return;
        }

        $exitDate = CarbonImmutable::parse($vehicle->exit_date->toDateString());
        $exitFr = $exitDate->format('d/m/Y');

        // Cas 2 : période entièrement avant exit_date → autorisé.
        if ($this->endDate->lessThan($exitDate)) {
            return;
        }

        // Cas 3 : période entièrement après exit_date.
        if ($this->startDate->greaterThanOrEqualTo($exitDate)) {
            $fail(sprintf(
                'Véhicule retiré depuis le %s. Aucune période n\'est autorisée à partir de cette date.',
                $exitFr,
            ));

            return;
        }

        // Cas 4 : période chevauche exit_date (start < exit_date <= end).
        $fail(sprintf(
            'Véhicule retiré le %s. La période ne peut pas dépasser cette date.',
            $exitFr,
        ));
    }
}
