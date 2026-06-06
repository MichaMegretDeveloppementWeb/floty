import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useMultiSelectFilter } from '@/Composables/Shared/useMultiSelectFilter';

function setup(allowFreeEntry: boolean, initial: string[] = []) {
    const sel = ref<string[]>(initial);
    const options = [
        { value: 'maintenance', label: 'Maintenance' },
        { value: 'other', label: 'Personnalisé' },
        { value: 'theft', label: 'Vol' },
    ];
    const filter = useMultiSelectFilter({
        selected: () => sel.value,
        options: () => options,
        allowFreeEntry,
        onChange: (next) => {
            sel.value = next;
        },
    });

    return { sel, filter };
}

describe('useMultiSelectFilter', () => {
    it('ajoute une valeur et vide la saisie', () => {
        const { sel, filter } = setup(false);
        filter.query.value = 'main';

        filter.add('maintenance');

        expect(sel.value).toEqual(['maintenance']);
        expect(filter.query.value).toBe('');
    });

    it('ne duplique pas une valeur déjà sélectionnée', () => {
        const { sel, filter } = setup(false, ['maintenance']);

        filter.add('maintenance');

        expect(sel.value).toEqual(['maintenance']);
    });

    it('retire une valeur', () => {
        const { sel, filter } = setup(false, ['maintenance', 'theft']);

        filter.remove('maintenance');

        expect(sel.value).toEqual(['theft']);
    });

    it('filtre les suggestions par préfixe (insensible à la casse) et exclut les sélectionnées', () => {
        const { filter } = setup(false, ['theft']);
        filter.query.value = 'p';

        const labels = filter.filteredOptions.value.map((o) => o.label);

        // "Personnalisé" matche "p", "Vol" est sélectionné (exclu), "Maintenance" ne matche pas.
        expect(labels).toEqual(['Personnalisé']);
    });

    it('expose le label de l\'option pour les chips, la valeur brute sinon', () => {
        const { filter } = setup(true, ['maintenance', 'Pneus']);

        const chips = filter.selectedChips.value;

        expect(chips[0]).toEqual({ value: 'maintenance', label: 'Maintenance' });
        // Free-text value with no matching option → label = value.
        expect(chips[1]).toEqual({ value: 'Pneus', label: 'Pneus' });
    });

    it('interdit la saisie libre quand allowFreeEntry est faux', () => {
        const { sel, filter } = setup(false);
        filter.query.value = 'Inconnu';

        expect(filter.canCommitFreeEntry.value).toBe(false);
        filter.commitFreeEntry();

        expect(sel.value).toEqual([]);
    });

    it('permet la saisie libre quand allowFreeEntry est vrai', () => {
        const { sel, filter } = setup(true);
        filter.query.value = '  Pneus  ';

        expect(filter.canCommitFreeEntry.value).toBe(true);
        filter.commitFreeEntry();

        expect(sel.value).toEqual(['Pneus']);
        expect(filter.query.value).toBe('');
    });

    it('ne commit pas une saisie libre déjà présente (insensible à la casse)', () => {
        const { sel, filter } = setup(true, ['Entretien']);
        filter.query.value = 'entretien';

        expect(filter.canCommitFreeEntry.value).toBe(false);
        filter.commitFreeEntry();

        expect(sel.value).toEqual(['Entretien']);
    });
});
