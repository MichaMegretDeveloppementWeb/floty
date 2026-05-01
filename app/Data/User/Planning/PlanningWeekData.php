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
     * @param  bool  $hasUnavailability  Vrai ssi la semaine porte au moins
     *                                   un jour d'indispo (tous types
     *                                   confondus). Alimente la bordure
     *                                   rouge du drawer pour cohérence
     *                                   visuelle avec la heatmap (ADR-0019
     *                                   § 2 D5).
     */
    public function __construct(
        public int $weekNumber,
        public string $weekStart,
        public string $weekEnd,
        public int $vehicleId,
        public string $licensePlate,
        public array $days,
        public array $companiesOnWeek,
        public bool $hasUnavailability,
    ) {}
}
