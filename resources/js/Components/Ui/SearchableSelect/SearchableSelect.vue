<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, useId, watch } from 'vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import { useSearchableSelect } from '@/Composables/Ui/SearchableSelect/useSearchableSelect';
import type { SelectOption } from '@/Composables/Ui/SearchableSelect/useSearchableSelect';

const props = withDefaults(
    defineProps<{
        label?: string;
        options: SelectOption[];
        placeholder?: string;
        hint?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        id?: string;
        searchPlaceholder?: string;
        noResultsLabel?: string;
        /**
         * Auto-open the dropdown on mount. If the component is `disabled`
         * at mount time, waits for it to flip to `false` and then opens
         * once (watcher self-disposes). Used by DriversMultiPicker to
         * avoid a double-click after "Add a driver".
         */
        autoOpenOnMount?: boolean;
        /**
         * Render the dropdown panel in the document flow instead of
         * absolutely positioned. Use inside a short scrollable container
         * (e.g. a FilterPopover): the panel then grows the container up to
         * its max-height instead of overflowing it as a clipped, cramped
         * floating list. Forms keep the default absolute overlay so opening
         * the panel does not shift the surrounding fields.
         */
        dropdownInFlow?: boolean;
    }>(),
    {
        disabled: false,
        required: false,
        searchPlaceholder: 'Rechercher…',
        noResultsLabel: 'Aucun résultat',
        autoOpenOnMount: false,
        dropdownInFlow: false,
    },
);

const modelValue = defineModel<string | number | null>({ required: true });

const autoId = useId();
const inputId = computed<string>(() => props.id ?? autoId);
const errorId = computed<string>(() => `${inputId.value}-error`);
const hintId = computed<string>(() => `${inputId.value}-hint`);
const panelId = computed<string>(() => `${inputId.value}-panel`);
const optionDomId = (idx: number): string => `${inputId.value}-opt-${idx}`;

const describedBy = computed<string | undefined>(() => {
    const ids: string[] = [];

    if (props.hint) {
        ids.push(hintId.value);
    }

    if (props.error) {
        ids.push(errorId.value);
    }

    return ids.length ? ids.join(' ') : undefined;
});

const rootRef = ref<HTMLElement | null>(null);
const triggerRef = ref<HTMLButtonElement | null>(null);
const searchRef = ref<HTMLInputElement | null>(null);

const optionsRef = computed<readonly SelectOption[]>(() => props.options);

const {
    isOpen,
    query,
    highlightedIndex,
    filteredOptions,
    selectedOption,
    open,
    toggle,
    selectByIndex,
    onKeyDown,
} = useSearchableSelect(rootRef, optionsRef, modelValue, (value) => {
    modelValue.value = value;
});

// Focus the search input on open, return focus to the trigger on close
// (keyboard UX: Escape must bring focus back to the trigger).
watch(isOpen, async (open) => {
    if (open) {
        await nextTick();
        searchRef.value?.focus();
    } else {
        triggerRef.value?.focus();
    }
});

const triggerStateClasses = computed<string>(() => {
    if (props.error) {
        return 'cursor-pointer border-rose-600 text-rose-700 focus-visible:ring-2 focus-visible:ring-rose-200';
    }

    if (props.disabled) {
        return 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400';
    }

    return 'cursor-pointer border-slate-200 text-slate-900 hover:border-slate-300 focus-visible:border-slate-400 focus-visible:ring-2 focus-visible:ring-slate-200';
});

function onTriggerClick(): void {
    if (props.disabled) {
        return;
    }

    toggle();
}

// Auto-open on mount, waiting for an initial `disabled` to flip to false.
onMounted(() => {
    if (!props.autoOpenOnMount) {
        return;
    }

    if (!props.disabled) {
        open();

        return;
    }

    // Wait for async-loaded options. The watcher self-disposes after the
    // first open to avoid re-opening on later `disabled` toggles.
    const stop = watch(
        () => props.disabled,
        (isDisabled) => {
            if (!isDisabled) {
                open();
                stop();
            }
        },
    );
});

function onTriggerKeyDown(event: KeyboardEvent): void {
    if (props.disabled) {
        return;
    }

    if (!isOpen.value) {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
            event.preventDefault();
            open();
        }

        return;
    }

    onKeyDown(event);
}
</script>

<template>
    <div ref="rootRef" class="flex flex-col gap-1.5">
        <FieldLabel v-if="label" :for="inputId" :required="required">
            {{ label }}
        </FieldLabel>
        <div class="relative">
            <button
                :id="inputId"
                ref="triggerRef"
                type="button"
                role="combobox"
                aria-haspopup="listbox"
                :aria-expanded="isOpen"
                :aria-controls="panelId"
                :aria-activedescendant="
                    isOpen && filteredOptions[highlightedIndex]
                        ? optionDomId(highlightedIndex)
                        : undefined
                "
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                :disabled="disabled"
                :class="[
                    'w-full appearance-none rounded-lg border bg-white pr-9 pl-3 py-2 text-base leading-tight transition-colors duration-[120ms] ease-out focus:outline-none text-left',
                    triggerStateClasses,
                ]"
                @click="onTriggerClick"
                @keydown="onTriggerKeyDown"
            >
                <span
                    :class="[
                        'block truncate',
                        selectedOption ? '' : 'text-slate-400',
                    ]"
                >
                    <slot
                        v-if="selectedOption"
                        name="selected"
                        :option="selectedOption"
                    >
                        {{ selectedOption.label }}
                    </slot>
                    <template v-else>{{ placeholder ?? '' }}</template>
                </span>
            </button>
            <ChevronDown
                :size="14"
                :stroke-width="1.75"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            />

            <div
                v-if="isOpen"
                :id="panelId"
                :class="[
                    'mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg',
                    dropdownInFlow ? '' : 'absolute top-full right-0 left-0 z-20',
                ]"
            >
                <div class="border-b border-slate-100 p-2">
                    <input
                        ref="searchRef"
                        v-model="query"
                        type="search"
                        role="searchbox"
                        aria-label="Filtrer les options"
                        :placeholder="searchPlaceholder"
                        class="w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm leading-tight focus:outline-none focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)]"
                        @keydown="onKeyDown"
                    />
                </div>

                <ul
                    role="listbox"
                    class="max-h-72 overflow-y-auto py-1"
                >
                    <li
                        v-for="(option, idx) in filteredOptions"
                        :id="optionDomId(idx)"
                        :key="option.value"
                        role="option"
                        :aria-selected="option.value === modelValue"
                        :class="[
                            'cursor-pointer px-3 py-1.5 text-sm transition-colors',
                            idx === highlightedIndex ? 'bg-slate-100' : '',
                            option.value === modelValue
                                ? 'bg-indigo-50 text-indigo-900'
                                : 'text-slate-900',
                        ]"
                        @click="selectByIndex(idx)"
                        @mouseenter="highlightedIndex = idx"
                    >
                        <slot
                            name="option"
                            :option="option"
                            :is-highlighted="idx === highlightedIndex"
                            :is-selected="option.value === modelValue"
                        >
                            {{ option.label }}
                        </slot>
                    </li>
                    <li
                        v-if="filteredOptions.length === 0"
                        class="px-3 py-2 text-sm text-slate-500"
                    >
                        {{ noResultsLabel }}
                    </li>
                </ul>
            </div>
        </div>

        <InputError v-if="error" :id="errorId" :message="error" />
        <p
            v-else-if="hint"
            :id="hintId"
            class="text-xs text-slate-500"
        >
            {{ hint }}
        </p>
    </div>
</template>
