<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Réponse de l'endpoint `GET /app/planning/week` — détail d'une
 * semaine pour un véhicule donné, consommée par le drawer planning.
 */
#[TypeScript]
final class PlanningWeekData extends Data
{
    /**
     * @param  list<WeekDaySlotData>  $days
     * @param  list<WeekCompanyPresenceData>  $companiesOnWeek
     * @param  list<string>  $vehicleBusyDates  Toutes les dates ISO Y-m-d
     *                                          de l'année fiscale où le
     *                                          véhicule porte déjà un
     *                                          contrat actif. Alimente le
     *                                          `disabled-dates` du
     *                                          DateRangePicker pour
     *                                          empêcher la sélection
     *                                          conflictuelle hors fenêtre
     *                                          de la semaine affichée.
     */
    public function __construct(
        public int $weekNumber,
        public string $weekStart,
        public string $weekEnd,
        public int $vehicleId,
        public string $licensePlate,
        public array $days,
        public array $companiesOnWeek,
        public array $vehicleBusyDates,
    ) {}
}
