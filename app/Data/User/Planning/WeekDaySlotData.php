<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Slot d'un jour dans la grille semaine du drawer planning.
 *
 * - `contract` est `null` quand le jour est libre.
 * - `hasUnavailability` est `true` ssi au moins une indispo (tous types
 *   confondus) couvre ce jour précisément. Alimente la bordure rouge
 *   du slot dans la grille « État de la semaine » (ADR-0019 § 2 D5).
 */
#[TypeScript]
final class WeekDaySlotData extends Data
{
    public function __construct(
        public string $date,
        public string $dayLabel,
        public ?WeekDayContractData $contract,
        public bool $hasUnavailability,
    ) {}
}
