import { onClickOutside, refDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type SelectOption = {
    value: string | number;
    label: string;
};

/**
 * Filtre `includes()` insensible à la casse sur le `label` des options.
 * Pure pour faciliter les tests Vitest isolés.
 */
export function filterOptions(
    options: readonly SelectOption[],
    query: string,
): SelectOption[] {
    const q = query.trim().toLowerCase();

    if (q === '') {
        return [...options];
    }

    return options.filter((opt) => opt.label.toLowerCase().includes(q));
}

/**
 * État + handlers du composant `SearchableSelect`. La logique est ici
 * pour respecter la convention « strict minimum dans les .vue ».
 *
 * Le composant fournit :
 * - `rootRef` : la racine, pour câbler le `onClickOutside`
 * - `options` / `modelValue` : reactive sources, lues mais jamais mutées
 * - `onSelect` : callback déclenché à la sélection (le composant met à
 *   jour `modelValue` via `defineModel`, ce composable n'a pas à le
 *   savoir)
 *
 * Le filtre est debouncé 300 ms (cf. plan 04.I.1) via `refDebounced` de
 * `@vueuse/core` : la frappe utilisateur reste fluide, le `computed`
 * `filteredOptions` ne se met à jour qu'après le délai.
 */
export function useSearchableSelect(
    rootRef: Readonly<Ref<HTMLElement | null>>,
    options: Readonly<Ref<readonly SelectOption[]>>,
    modelValue: Readonly<Ref<string | number | null>>,
    onSelect: (value: string | number) => void,
): {
    isOpen: Ref<boolean>;
    query: Ref<string>;
    highlightedIndex: Ref<number>;
    filteredOptions: ComputedRef<SelectOption[]>;
    selectedOption: ComputedRef<SelectOption | null>;
    open: () => void;
    close: () => void;
    toggle: () => void;
    selectByIndex: (index: number) => void;
    onKeyDown: (event: KeyboardEvent) => void;
} {
    const isOpen = ref<boolean>(false);
    const query = ref<string>('');
    const debouncedQuery = refDebounced(query, 300);
    const highlightedIndex = ref<number>(0);

    const filteredOptions = computed<SelectOption[]>(() =>
        filterOptions(options.value, debouncedQuery.value),
    );

    const selectedOption = computed<SelectOption | null>(
        () => options.value.find((o) => o.value === modelValue.value) ?? null,
    );

    const open = (): void => {
        if (isOpen.value) {
            return;
        }

        isOpen.value = true;
        query.value = '';

        // Initialise `highlightedIndex` sur l'option sélectionnée si
        // elle existe (UX : la sélection courante est mise en évidence
        // dès l'ouverture). Sinon on part de 0.
        const selectedIdx = options.value.findIndex(
            (o) => o.value === modelValue.value,
        );
        highlightedIndex.value = selectedIdx >= 0 ? selectedIdx : 0;
    };

    const close = (): void => {
        isOpen.value = false;
        query.value = '';
        highlightedIndex.value = 0;
    };

    const toggle = (): void => {
        if (isOpen.value) {
            close();
        } else {
            open();
        }
    };

    const selectByIndex = (index: number): void => {
        const opt = filteredOptions.value[index];

        if (opt === undefined) {
            return;
        }

        onSelect(opt.value);
        close();
    };

    // Quand le filtre change, ramène `highlightedIndex` dans les bornes
    // pour éviter qu'il pointe au-delà de la liste filtrée.
    watch(filteredOptions, (next) => {
        if (highlightedIndex.value >= next.length) {
            highlightedIndex.value = Math.max(0, next.length - 1);
        }
    });

    const onKeyDown = (event: KeyboardEvent): void => {
        if (!isOpen.value) {
            return;
        }

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();

                if (highlightedIndex.value < filteredOptions.value.length - 1) {
                    highlightedIndex.value++;
                }

                break;
            case 'ArrowUp':
                event.preventDefault();

                if (highlightedIndex.value > 0) {
                    highlightedIndex.value--;
                }

                break;
            case 'Enter':
                event.preventDefault();
                selectByIndex(highlightedIndex.value);
                break;
            case 'Escape':
                event.preventDefault();
                close();
                break;
        }
    };

    onClickOutside(rootRef, close);

    return {
        isOpen,
        query,
        highlightedIndex,
        filteredOptions,
        selectedOption,
        open,
        close,
        toggle,
        selectByIndex,
        onKeyDown,
    };
}
