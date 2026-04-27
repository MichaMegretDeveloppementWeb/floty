<?php

declare(strict_types=1);

namespace App\Enums\Company;

/**
 * Activité exonérée d'une entreprise utilisatrice (R-2024-022).
 *
 * Si une entreprise exerce l'une de ces activités ET qu'un véhicule
 * de la flotte y est affecté à 100 %, l'exonération s'applique
 * (CIBS art. L. 421-131 / L. 421-143).
 *
 * **Inactif par défaut en V1** : aucune entreprise utilisatrice du
 * périmètre Floty actuel ne déclare ce type d'activité. La règle est
 * structurellement câblée pour ne pas avoir à migrer dans une phase
 * future.
 */
enum ExemptedActivity: string
{
    case None = 'none';
    case TransportPublic = 'transport_public';
    case Agricultural = 'agricultural';
    case DrivingSchool = 'driving_school';
    case Racing = 'racing';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucune (activité commerciale standard)',
            self::TransportPublic => 'Transport public de personnes',
            self::Agricultural => 'Activité agricole / forestière',
            self::DrivingSchool => 'Enseignement de la conduite',
            self::Racing => 'Compétitions sportives',
        };
    }
}
