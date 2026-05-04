<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;

/**
 * Calcule la liste des effets de bord d'une édition de VFC sur ses
 * voisines de l'historique d'un véhicule.
 *
 * Pure fonction (pas d'I/O, pas d'état) - l'Action passe l'historique
 * complet du véhicule (sauf la VFC en cours d'édition) et les
 * nouvelles bornes proposées, et reçoit la liste des `Delete` /
 * `AdjustEffectiveFrom` / `AdjustEffectiveTo` à appliquer après le
 * `UPDATE` de la VFC.
 *
 * Algorithme :
 *
 *  1. Pour chaque autre VFC `v` du véhicule, classer la position de
 *     ses bornes par rapport à `[newFrom, newTo]` :
 *     - si `v.range` est entièrement engloutie → `Delete`
 *     - si `v` chevauche par la **gauche** (commence avant `newFrom`,
 *       finit dedans) → `AdjustEffectiveTo` à `newFrom − 1`
 *     - si `v` chevauche par la **droite** (commence dedans, finit
 *       après `newTo`) → `AdjustEffectiveFrom` à `newTo + 1`
 *     - si `v` est entièrement avant ou entièrement après → candidate
 *       prédécesseur ou successeur immédiat
 *
 *  2. Une fois les chevauchements résolus, comblement automatique des
 *     trous immédiats avec le prédécesseur / successeur retenu :
 *     - si l'écart entre `predecessor.effective_to` et `newFrom − 1`
 *       est non nul → `AdjustEffectiveTo`
 *     - si l'écart entre `newTo + 1` et `successor.effective_from`
 *       est non nul → `AdjustEffectiveFrom`
 *
 * Les bornes ouvertes côté droit (`effective_to === null`, version
 * courante) sont normalisées à `+∞` pour les comparaisons.
 */
final readonly class FiscalCharacteristicsImpactComputer
{
    /**
     * @param  iterable<VehicleFiscalCharacteristics>  $others  Toutes les VFC du véhicule sauf l'éditée
     * @return list<FiscalCharacteristicsImpact>
     */
    public function compute(
        iterable $others,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): array {
        /** @var list<FiscalCharacteristicsImpact> $impacts */
        $impacts = [];

        $candidatePredecessor = null;
        $candidateSuccessor = null;
        $candidatePredecessorFrom = null;
        $candidateSuccessorFrom = null;

        foreach ($others as $v) {
            $vFrom = CarbonImmutable::parse($v->effective_from->toDateString());
            $vTo = $v->effective_to === null
                ? null
                : CarbonImmutable::parse($v->effective_to->toDateString());

            // Engulfment : v est strictement contenu (au sens large) dans [newFrom, newTo]
            if ($this->isEngulfedBy($vFrom, $vTo, $newFrom, $newTo)) {
                $impacts[] = FiscalCharacteristicsImpact::delete($v);

                continue;
            }

            // Chevauchement par la gauche : v commence avant newFrom et son
            // effective_to tombe dans [newFrom, newTo]
            if (
                $vFrom->lessThan($newFrom)
                && $vTo !== null
                && $vTo->greaterThanOrEqualTo($newFrom)
                && ($newTo === null || $vTo->lessThanOrEqualTo($newTo))
            ) {
                $impacts[] = FiscalCharacteristicsImpact::adjustEffectiveTo(
                    $v,
                    $newFrom->subDay(),
                );

                continue;
            }

            // Chevauchement par la droite : v commence dans [newFrom, newTo]
            // et finit après newTo (ou est ouvert vers le futur)
            if (
                $newTo !== null
                && $vFrom->greaterThanOrEqualTo($newFrom)
                && $vFrom->lessThanOrEqualTo($newTo)
                && ($vTo === null || $vTo->greaterThan($newTo))
            ) {
                $impacts[] = FiscalCharacteristicsImpact::adjustEffectiveFrom(
                    $v,
                    $newTo->addDay(),
                );

                continue;
            }

            // Pas de chevauchement → entièrement avant ou entièrement après
            if ($vTo !== null && $vTo->lessThan($newFrom)) {
                if (
                    $candidatePredecessorFrom === null
                    || $vFrom->greaterThan($candidatePredecessorFrom)
                ) {
                    $candidatePredecessor = $v;
                    $candidatePredecessorFrom = $vFrom;
                }

                continue;
            }

            if ($newTo !== null && $vFrom->greaterThan($newTo)) {
                if (
                    $candidateSuccessorFrom === null
                    || $vFrom->lessThan($candidateSuccessorFrom)
                ) {
                    $candidateSuccessor = $v;
                    $candidateSuccessorFrom = $vFrom;
                }

                continue;
            }

            // Cas pathologique : v contient strictement [newFrom, newTo]
            // (vFrom < newFrom AND (vTo == null OR vTo > newTo)).
            // L'invariant "deux VFC qui se chevauchent" ne devrait jamais
            // permettre cette configuration en production, mais on
            // l'ignore défensivement plutôt que de produire un impact
            // incorrect. Le `guardNoOverlapResidual` côté Action ré-attrape
            // ces cas exotiques.
        }

        // Comblement immédiat du trou avec le prédécesseur retenu
        if ($candidatePredecessor !== null) {
            $expectedTo = $newFrom->subDay();
            $currentTo = $candidatePredecessor->effective_to !== null
                ? CarbonImmutable::parse($candidatePredecessor->effective_to->toDateString())
                : null;

            if ($currentTo === null || ! $currentTo->equalTo($expectedTo)) {
                $impacts[] = FiscalCharacteristicsImpact::adjustEffectiveTo(
                    $candidatePredecessor,
                    $expectedTo,
                );
            }
        }

        // Comblement immédiat du trou avec le successeur retenu
        // (uniquement si la nouvelle plage a une borne droite définie ;
        // sinon il n'y a pas de "successeur" possible).
        if ($candidateSuccessor !== null && $newTo !== null) {
            $expectedFrom = $newTo->addDay();
            $currentFrom = CarbonImmutable::parse($candidateSuccessor->effective_from->toDateString());

            if (! $currentFrom->equalTo($expectedFrom)) {
                $impacts[] = FiscalCharacteristicsImpact::adjustEffectiveFrom(
                    $candidateSuccessor,
                    $expectedFrom,
                );
            }
        }

        return $impacts;
    }

    private function isEngulfedBy(
        CarbonImmutable $vFrom,
        ?CarbonImmutable $vTo,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): bool {
        if ($vFrom->lessThan($newFrom)) {
            return false;
        }

        // newTo == null → newRange = [newFrom, +∞), tout vTo (même null)
        // est ≤ +∞ → engulfed dès que vFrom ≥ newFrom.
        if ($newTo === null) {
            return true;
        }

        // newTo défini : v doit avoir une borne droite et celle-ci doit
        // être ≤ newTo.
        return $vTo !== null && $vTo->lessThanOrEqualTo($newTo);
    }
}
