import { describe, expect, it } from 'vitest';
import { useControlLabels } from '@/Composables/Control/useControlLabels';

type EnumOption = App.Data.User.Vehicle.EnumOptionData;
type ControlDefinition = App.Data.User.Control.ControlDefinitionData;

const anchorOptions: EnumOption[] = [
    { value: 'first_origin_registration', label: '1re mise en circulation (origine)' },
    { value: 'acquisition', label: "Date d'acquisition" },
];

const durationUnitOptions: EnumOption[] = [
    { value: 'years', label: 'ans' },
    { value: 'months', label: 'mois' },
];

function makeControl(overrides: Partial<ControlDefinition> = {}): ControlDefinition {
    return {
        id: 1,
        name: 'Contrôle technique',
        anchor: 'first_origin_registration',
        initialDurationValue: 4,
        initialDurationUnit: 'years',
        cycleValue: 2,
        cycleUnit: 'years',
        notifyDriver: false,
        impliesUnavailability: true,
        customizeReminders: false,
        reminderDaysBefore: null,
        reminderOnDueDay: null,
        reminderRepeatEveryDays: null,
        isActive: true,
        displayOrder: 0,
        ownRecipients: [],
        excludedDefaultEmails: [],
        ...overrides,
    };
}

describe('useControlLabels', () => {
    it('mappe la valeur d\'ancre vers son label backend', () => {
        const { anchorLabel } = useControlLabels(anchorOptions, durationUnitOptions);

        expect(anchorLabel('first_origin_registration')).toBe('1re mise en circulation (origine)');
        expect(anchorLabel('acquisition')).toBe("Date d'acquisition");
    });

    it('retombe sur la valeur brute pour une ancre inconnue', () => {
        const { anchorLabel } = useControlLabels(anchorOptions, durationUnitOptions);

        expect(anchorLabel('inconnu')).toBe('inconnu');
    });

    it('mappe les unités de durée', () => {
        const { unitLabel } = useControlLabels(anchorOptions, durationUnitOptions);

        expect(unitLabel('years')).toBe('ans');
        expect(unitLabel('months')).toBe('mois');
    });

    it('rend un résumé d\'échéance lisible', () => {
        const { echeanceSummary } = useControlLabels(anchorOptions, durationUnitOptions);

        expect(echeanceSummary(makeControl())).toBe('Validité initiale 4 ans, puis tous les 2 ans');
        expect(echeanceSummary(makeControl({ cycleValue: 6, cycleUnit: 'months' })))
            .toBe('Validité initiale 4 ans, puis tous les 6 mois');
    });
});
