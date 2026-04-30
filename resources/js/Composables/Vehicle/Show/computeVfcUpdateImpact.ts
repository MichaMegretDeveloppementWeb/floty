type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;

export type VfcImpact =
    | {
          type: 'delete';
          targetId: number;
          targetEffectiveFrom: string;
          targetEffectiveTo: string | null;
      }
    | {
          type: 'adjust_effective_to';
          targetId: number;
          targetEffectiveFrom: string;
          targetEffectiveTo: string | null;
          newEffectiveTo: string;
      }
    | {
          type: 'adjust_effective_from';
          targetId: number;
          targetEffectiveFrom: string;
          targetEffectiveTo: string | null;
          newEffectiveFrom: string;
      };

/**
 * Mirroir TS de {@see App\Services\Vehicle\FiscalCharacteristicsImpactComputer}.
 *
 * Sert exclusivement à la **prévisualisation côté UI** (modale de
 * confirmation, summary inline). Le backend re-calcule la même cascade
 * en production et fait autorité (le front ne peut pas court-circuiter
 * la confirmation : si un `Delete` est calculé sans `confirmed=true`,
 * le serveur lève une exception et la cascade n'est pas appliquée).
 *
 * Les deux implémentations DOIVENT rester strictement équivalentes.
 * Toute modification de l'algorithme PHP doit être répliquée ici.
 */
export function computeVfcUpdateImpact(
    history: ReadonlyArray<Vfc>,
    editingId: number,
    newFrom: string,
    newTo: string | null,
): VfcImpact[] {
    const impacts: VfcImpact[] = [];

    if (newFrom === '') {
        return impacts;
    }

    const others = history.filter((v) => v.id !== editingId);

    let candidatePredecessor: Vfc | null = null;
    let candidateSuccessor: Vfc | null = null;

    for (const v of others) {
        const vFrom = v.effectiveFrom;
        const vTo = v.effectiveTo;

        if (isEngulfedBy(vFrom, vTo, newFrom, newTo)) {
            impacts.push({
                type: 'delete',
                targetId: v.id,
                targetEffectiveFrom: vFrom,
                targetEffectiveTo: vTo,
            });
            continue;
        }

        // Chevauchement par la gauche : v commence avant newFrom et finit dans [newFrom, newTo]
        if (
            vFrom < newFrom
            && vTo !== null
            && vTo >= newFrom
            && (newTo === null || vTo <= newTo)
        ) {
            impacts.push({
                type: 'adjust_effective_to',
                targetId: v.id,
                targetEffectiveFrom: vFrom,
                targetEffectiveTo: vTo,
                newEffectiveTo: subDay(newFrom),
            });
            continue;
        }

        // Chevauchement par la droite : v commence dans [newFrom, newTo] et finit après newTo
        if (
            newTo !== null
            && vFrom >= newFrom
            && vFrom <= newTo
            && (vTo === null || vTo > newTo)
        ) {
            impacts.push({
                type: 'adjust_effective_from',
                targetId: v.id,
                targetEffectiveFrom: vFrom,
                targetEffectiveTo: vTo,
                newEffectiveFrom: addDay(newTo),
            });
            continue;
        }

        // Pas de chevauchement → entièrement avant ou entièrement après
        if (vTo !== null && vTo < newFrom) {
            if (
                candidatePredecessor === null
                || vFrom > candidatePredecessor.effectiveFrom
            ) {
                candidatePredecessor = v;
            }

            continue;
        }

        if (newTo !== null && vFrom > newTo) {
            if (
                candidateSuccessor === null
                || vFrom < candidateSuccessor.effectiveFrom
            ) {
                candidateSuccessor = v;
            }
        }
    }

    // Comblement immédiat du trou avec le prédécesseur retenu
    if (candidatePredecessor !== null) {
        const expectedTo = subDay(newFrom);
        const currentTo = candidatePredecessor.effectiveTo;

        if (currentTo === null || currentTo !== expectedTo) {
            impacts.push({
                type: 'adjust_effective_to',
                targetId: candidatePredecessor.id,
                targetEffectiveFrom: candidatePredecessor.effectiveFrom,
                targetEffectiveTo: candidatePredecessor.effectiveTo,
                newEffectiveTo: expectedTo,
            });
        }
    }

    if (candidateSuccessor !== null && newTo !== null) {
        const expectedFrom = addDay(newTo);
        const currentFrom = candidateSuccessor.effectiveFrom;

        if (currentFrom !== expectedFrom) {
            impacts.push({
                type: 'adjust_effective_from',
                targetId: candidateSuccessor.id,
                targetEffectiveFrom: candidateSuccessor.effectiveFrom,
                targetEffectiveTo: candidateSuccessor.effectiveTo,
                newEffectiveFrom: expectedFrom,
            });
        }
    }

    return impacts;
}

function isEngulfedBy(
    vFrom: string,
    vTo: string | null,
    newFrom: string,
    newTo: string | null,
): boolean {
    if (vFrom < newFrom) {
        return false;
    }

    if (newTo === null) {
        return true;
    }

    return vTo !== null && vTo <= newTo;
}

function addDay(date: string): string {
    const [y, m, d] = date.split('-').map(Number) as [number, number, number];
    const dt = new Date(Date.UTC(y, m - 1, d));
    dt.setUTCDate(dt.getUTCDate() + 1);

    return dt.toISOString().slice(0, 10);
}

function subDay(date: string): string {
    const [y, m, d] = date.split('-').map(Number) as [number, number, number];
    const dt = new Date(Date.UTC(y, m - 1, d));
    dt.setUTCDate(dt.getUTCDate() - 1);

    return dt.toISOString().slice(0, 10);
}

export function describeImpact(impact: VfcImpact): string {
    const period = formatPeriod(impact.targetEffectiveFrom, impact.targetEffectiveTo);

    switch (impact.type) {
        case 'delete':
            return `Suppression de la version ${period}`;
        case 'adjust_effective_to':
            return `Date de fin de la version ${period} ramenée au ${formatDate(impact.newEffectiveTo)}`;
        case 'adjust_effective_from':
            return `Date de début de la version ${period} ramenée au ${formatDate(impact.newEffectiveFrom)}`;
    }
}

export function hasDestructiveImpact(impacts: ReadonlyArray<VfcImpact>): boolean {
    return impacts.some((i) => i.type === 'delete');
}

function formatDate(date: string): string {
    const [y, m, d] = date.split('-');

    return `${d}/${m}/${y}`;
}

function formatPeriod(from: string, to: string | null): string {
    if (to === null) {
        return `depuis le ${formatDate(from)}`;
    }

    return `du ${formatDate(from)} au ${formatDate(to)}`;
}
