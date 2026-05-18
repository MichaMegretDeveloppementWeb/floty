<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Impact d'un contrat synthétique sur le loyer mensuel d'UNE entreprise
 * pour 1 mois civil donné (SC8 · 2026-05-18).
 *
 * Contexte UX · l'utilisateur en cours de saisie veut savoir « si je
 * valide cette location, qu'est-ce que ça donne sur la facture de
 * l'entreprise ? ». On expose donc, mois par mois touché par le contrat
 * synthétique ·
 *   - `existingNetCents` · loyer mensuel ACTUEL (post-réductions) de
 *     l'entreprise sur ce mois, sans le contrat synthétique
 *   - `newTotalCents` · nouveau total mensuel après ajout (= existing
 *     + contribution du contrat synthétique sur ce mois)
 *
 * **Caveat sémantique** · l'addition `existing + induced` suppose que
 * le contrat synthétique n'a pas de jours en commun avec un contrat
 * existant pour la même entreprise (sinon consolidation OptimalRateBreakdown
 * nécessaire). Le `DateRangePicker` du form bloque déjà les dates
 * occupées · l'hypothèse tient en pratique.
 */
#[TypeScript]
final class RentalMonthlyImpactData extends Data
{
    public function __construct(
        public int $year,
        public int $month,
        public int $existingNetCents,
        public int $newTotalCents,
    ) {}
}
