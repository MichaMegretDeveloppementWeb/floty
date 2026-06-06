import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

/**
 * A selectable value in a {@link useMultiSelectFilter}. `value` is what travels
 * in the model/URL (an enum value for a closed list, the literal text for a
 * free-text axis); `label` is what the user sees.
 */
export type MultiSelectOption = {
    value: string;
    label: string;
};

export type MultiSelectChip = MultiSelectOption;

/**
 * Logic for a multi-value filter combobox (chips + typed input + suggestion
 * dropdown), shared by the global events index and the per-vehicle timeline.
 *
 * - Suggestions = the real existing values, filtered by case-insensitive prefix
 *   on the typed text, minus the already-selected ones.
 * - `allowFreeEntry`: when true (free-text axis like « catégorie »), the typed
 *   text can be committed even if it matches no suggestion (string comparison,
 *   « la saisie fait foi »). When false (closed enum like « type »), only a
 *   suggestion can be added.
 * - The model is the list of selected VALUES; the component wires `onChange` to
 *   its `defineModel` emit.
 */
export function useMultiSelectFilter(opts: {
    selected: () => string[];
    options: () => readonly MultiSelectOption[];
    allowFreeEntry: boolean;
    onChange: (next: string[]) => void;
}): {
    query: Ref<string>;
    open: Ref<boolean>;
    selectedChips: ComputedRef<MultiSelectChip[]>;
    filteredOptions: ComputedRef<MultiSelectOption[]>;
    canCommitFreeEntry: ComputedRef<boolean>;
    add: (value: string) => void;
    remove: (value: string) => void;
    commitFreeEntry: () => void;
} {
    const query = ref<string>('');
    const open = ref<boolean>(false);

    const normalize = (value: string): string => value.trim().toLowerCase();

    const labelFor = (value: string): string =>
        opts.options().find((o) => o.value === value)?.label ?? value;

    const isSelected = (value: string): boolean =>
        opts.selected().some((v) => normalize(v) === normalize(value));

    const selectedChips = computed<MultiSelectChip[]>(() =>
        opts.selected().map((value) => ({ value, label: labelFor(value) })),
    );

    const filteredOptions = computed<MultiSelectOption[]>(() => {
        const typed = normalize(query.value);

        return opts.options().filter((option) => {
            if (isSelected(option.value)) {
                return false;
            }

            return typed === '' || normalize(option.label).startsWith(typed);
        });
    });

    const canCommitFreeEntry = computed<boolean>(() => {
        if (!opts.allowFreeEntry) {
            return false;
        }

        const typed = query.value.trim();

        return typed !== '' && !isSelected(typed);
    });

    function add(value: string): void {
        if (!isSelected(value)) {
            opts.onChange([...opts.selected(), value]);
        }

        query.value = '';
        open.value = false;
    }

    function remove(value: string): void {
        opts.onChange(opts.selected().filter((v) => v !== value));
    }

    function commitFreeEntry(): void {
        if (!canCommitFreeEntry.value) {
            return;
        }

        add(query.value.trim());
    }

    return {
        query,
        open,
        selectedChips,
        filteredOptions,
        canCommitFreeEntry,
        add,
        remove,
        commitFreeEntry,
    };
}
