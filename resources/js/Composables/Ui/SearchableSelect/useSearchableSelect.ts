import { onClickOutside, refDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type SelectOption = {
    value: string | number;
    label: string;
};

/**
 * Case-insensitive `includes()` filter over option labels. Pure for
 * isolated Vitest coverage.
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
 * State + handlers for `SearchableSelect`. Filter is debounced 300 ms via
 * `refDebounced` so typing stays fluid while `filteredOptions` only
 * updates after the delay.
 *
 * @param rootRef     Root element ref (wired to `onClickOutside`).
 * @param options     Reactive options source (read, never mutated).
 * @param modelValue  Reactive selected value (read, never mutated).
 * @param onSelect    Callback fired on selection. The host component
 *                    updates `modelValue` via `defineModel`.
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

        // Highlight the current selection on open, fall back to 0.
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

    // Clamp `highlightedIndex` when the filtered list shrinks.
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
