<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Slot d'un jour dans la grille semaine du drawer planning.
 *
 * - `contract` est `null` quand le jour est libre OU quand le jour est
 *   occupé par une autre entreprise et que le drawer est en mode
 *   company-locked (chantier P3) ; dans ce dernier cas
 *   `isOccupiedByOther` est `true`.
 * - `hasUnavailability` est `true` ssi au moins une indispo (tous types
 *   confondus) couvre ce jour précisément. Alimente la bordure rouge
 *   du slot dans la grille « État de la semaine » (ADR-0019 § 2 D5).
 * - `isOccupiedByOther` est `true` ssi le drawer est ouvert sur une
 *   Vue Entreprise et que le contrat occupant ce jour appartient à une
 *   autre entreprise (anonymisation : ni l'identité, ni la couleur de
 *   l'entreprise occupante ne fuitent côté frontend).
 */
#[TypeScript]
final class WeekDaySlotData extends Data
{
    public function __construct(
        public string $date,
        public string $dayLabel,
        public ?WeekDayContractData $contract,
        public bool $hasUnavailability,
        public bool $isOccupiedByOther = false,
    ) {}
}
