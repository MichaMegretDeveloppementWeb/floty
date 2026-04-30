import { describe, expect, it } from 'vitest';
import {
    computeVfcUpdateImpact,
    hasDestructiveImpact,
} from '@/Composables/Vehicle/Show/computeVfcUpdateImpact';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;

/**
 * Helper minimal — seules les bornes et l'id sont utilisés par
 * `computeVfcUpdateImpact`, on remplit le reste avec des valeurs
 * neutres pour satisfaire le type complet sans bruit dans les tests.
 */
function makeVfc(
    id: number,
    effectiveFrom: string,
    effectiveTo: string | null,
): Vfc {
    return {
        id,
        effectiveFrom,
        effectiveTo,
        isCurrent: effectiveTo === null,
        receptionCategory: 'M1',
        vehicleUserType: 'VP',
        bodyType: 'CI',
        seatsCount: 5,
        energySource: 'gasoline',
        underlyingCombustionEngineType: null,
        euroStandard: 'euro_6',
        pollutantCategory: 'category_1',
        homologationMethod: 'WLTP',
        co2Wltp: 120,
        co2Nedc: null,
        taxableHorsepower: null,
        kerbMass: null,
        handicapAccess: false,
        n1PassengerTransport: false,
        n1RemovableSecondRowSeat: false,
        m1SpecialUse: false,
        n1SkiLiftUse: false,
        changeReason: 'initial_creation',
        changeNote: null,
    };
}

/**
 * Mirroir de `FiscalCharacteristicsImpactComputerTest` côté backend.
 * Les deux suites doivent rester strictement équivalentes.
 */
describe('computeVfcUpdateImpact', () => {
    it("ne retourne aucun impact quand les bornes ne changent pas et l'historique est contigu", () => {
        const previous = makeVfc(1, '2023-01-01', '2023-12-31');
        const current = makeVfc(2, '2024-01-01', null);

        const impacts = computeVfcUpdateImpact(
            [previous, current],
            current.id,
            '2024-01-01',
            null,
        );

        expect(impacts).toHaveLength(0);
    });

    it("étend la précédente quand la VFC éditée est décalée en avant (gap)", () => {
        const previous = makeVfc(1, '2022-01-01', '2023-12-31');
        const current = makeVfc(2, '2024-01-01', null);

        const impacts = computeVfcUpdateImpact(
            [previous, current],
            current.id,
            '2024-02-15',
            null,
        );

        expect(impacts).toHaveLength(1);
        expect(impacts[0]!.type).toBe('adjust_effective_to');
        expect(impacts[0]!.targetId).toBe(previous.id);
        expect(
            impacts[0]!.type === 'adjust_effective_to'
                ? impacts[0]!.newEffectiveTo
                : null,
        ).toBe('2024-02-14');
    });

    it("raccourcit la précédente quand la VFC éditée recule partiellement dedans", () => {
        const previous = makeVfc(1, '2023-01-01', '2023-12-31');
        const current = makeVfc(2, '2024-01-01', null);

        const impacts = computeVfcUpdateImpact(
            [previous, current],
            current.id,
            '2023-06-15',
            null,
        );

        expect(impacts).toHaveLength(1);
        expect(impacts[0]!.type).toBe('adjust_effective_to');
        expect(
            impacts[0]!.type === 'adjust_effective_to'
                ? impacts[0]!.newEffectiveTo
                : null,
        ).toBe('2023-06-14');
    });

    it("supprime la précédente engloutie totalement", () => {
        const previous = makeVfc(1, '2023-01-15', '2023-03-31');
        const current = makeVfc(2, '2024-01-01', null);

        const impacts = computeVfcUpdateImpact(
            [previous, current],
            current.id,
            '2023-01-01',
            null,
        );

        expect(impacts).toHaveLength(1);
        expect(impacts[0]!.type).toBe('delete');
        expect(impacts[0]!.targetId).toBe(previous.id);
        expect(hasDestructiveImpact(impacts)).toBe(true);
    });

    it("multi-voisins : seul le prédécesseur immédiat est affecté par un gap-fill", () => {
        const v1 = makeVfc(1, '2020-01-01', '2020-12-31');
        const v2 = makeVfc(2, '2021-01-01', '2021-12-31');
        const v3 = makeVfc(3, '2022-01-01', '2022-12-31');
        const editing = makeVfc(99, '2023-01-01', null);

        const impacts = computeVfcUpdateImpact(
            [v1, v2, v3, editing],
            editing.id,
            '2024-01-01',
            null,
        );

        expect(impacts).toHaveLength(1);
        expect(impacts[0]!.targetId).toBe(v3.id);
        expect(impacts[0]!.type).toBe('adjust_effective_to');
    });

    it("décalage arrière de grande amplitude : supprime les versions intermédiaires et raccourcit la plus ancienne", () => {
        const v1 = makeVfc(1, '2020-01-01', '2020-12-31');
        const v2 = makeVfc(2, '2021-01-01', '2021-12-31');
        const v3 = makeVfc(3, '2022-01-01', '2022-12-31');
        const editing = makeVfc(99, '2023-01-01', null);

        const impacts = computeVfcUpdateImpact(
            [v1, v2, v3, editing],
            editing.id,
            '2020-06-15',
            null,
        );

        expect(impacts).toHaveLength(3);

        const byTarget = new Map(impacts.map((i) => [i.targetId, i]));
        expect(byTarget.get(v1.id)!.type).toBe('adjust_effective_to');
        expect(byTarget.get(v2.id)!.type).toBe('delete');
        expect(byTarget.get(v3.id)!.type).toBe('delete');
    });
});
