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
 * TS mirror of {@see App\Services\Vehicle\FiscalCharacteristicsImpactComputer}.
 *
 * UI preview only (confirmation modal, inline summary). The backend recomputes the same cascade
 * and is authoritative: the front-end cannot bypass confirmation, since a `Delete` computed
 * without `confirmed=true` triggers a server exception and the cascade is not applied.
 *
 * Both implementations MUST stay strictly equivalent. Any PHP algorithm change must be mirrored here.
 */
export function computeVfcUpdateImpact(
    history: ReadonlyArray<Vfc>,
    editingId: number | null,
    newFrom: string,
    newTo: string | null,
): VfcImpact[] {
    const impacts: VfcImpact[] = [];

    if (newFrom === '') {
        return impacts;
    }

    // editingId === null → create mode: no VFC to exclude from history (simulating a new range
    // inserted among existing ones). Edit mode excludes the VFC being edited.
    const others = editingId === null
        ? history
        : history.filter((v) => v.id !== editingId);

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

        // Left overlap: v starts before newFrom and ends in [newFrom, newTo].
        // Special case: v is the current (vTo=null) and the new range becomes the new current
        // (newTo=null) → shorten the old current to newFrom-1.
        if (
            vFrom < newFrom
            && (
                (vTo !== null && vTo >= newFrom && (newTo === null || vTo <= newTo))
                || (vTo === null && newTo === null)
            )
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

        // Right overlap: v starts in [newFrom, newTo] and ends after newTo.
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

        // No overlap → entirely before or entirely after.
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

    // Fill the gap with the chosen predecessor.
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

/**
 * TS mirror of the backend `guardNotStrictlyInsideExisting` guard
 * (see `Create/UpdateFiscalCharacteristicsAction`).
 *
 * Returns the VFC that strictly engulfs the new range, otherwise `null`.
 * Used UI-side to disable submit and show an explicit message before the user submits.
 * The backend re-catches this case as defense in depth.
 */
export function findStrictlyContainingVfc(
    history: ReadonlyArray<Vfc>,
    editingId: number | null,
    newFrom: string,
    newTo: string | null,
): Vfc | null {
    if (newFrom === '') {
        return null;
    }

    // newTo === null → the new range is open-ended and always extends past any existing on the right.
    // This case is handled by the ImpactComputer (left overlap), not by R4.
    if (newTo === null) {
        return null;
    }

    const others = editingId === null
        ? history
        : history.filter((v) => v.id !== editingId);

    for (const v of others) {
        if (!(v.effectiveFrom < newFrom)) {
            continue;
        }

        const endsAfterNewRange = v.effectiveTo === null
            || v.effectiveTo > newTo;

        if (endsAfterNewRange) {
            return v;
        }
    }

    return null;
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
