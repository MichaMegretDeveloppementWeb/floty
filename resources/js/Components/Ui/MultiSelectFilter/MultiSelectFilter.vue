<script setup lang="ts">
/**
 * Multi-value filter combobox: selected values as removable chips, a typed
 * input, and a styled suggestion dropdown of the real existing values (prefix
 * filtered). `allow-free-entry` lets the user commit a typed value that matches
 * no suggestion (free-text axis like « catégorie »); a closed axis (« type »)
 * only accepts suggestions. Logic lives in `useMultiSelectFilter`.
 */
import { Plus, X } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import { useMultiSelectFilter } from '@/Composables/Shared/useMultiSelectFilter';
import type { MultiSelectOption } from '@/Composables/Shared/useMultiSelectFilter';

const props = withDefaults(
    defineProps<{
        label: string;
        options: readonly MultiSelectOption[];
        allowFreeEntry?: boolean;
        placeholder?: string;
    }>(),
    {
        allowFreeEntry: false,
        placeholder: 'Rechercher...',
    },
);

const model = defineModel<string[]>({ required: true });

const {
    query,
    open,
    selectedChips,
    filteredOptions,
    canCommitFreeEntry,
    add,
    remove,
    commitFreeEntry,
} = useMultiSelectFilter({
    selected: () => model.value,
    options: () => props.options,
    allowFreeEntry: props.allowFreeEntry,
    onChange: (next) => (model.value = next),
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-xs font-medium text-slate-500">{{ label }}</label>

        <div class="flex flex-wrap items-center gap-1.5">
            <Badge
                v-for="chip in selectedChips"
                :key="chip.value"
                tone="slate"
                :uppercase="false"
            >
                <span class="inline-flex items-center gap-1">
                    {{ chip.label }}
                    <button
                        type="button"
                        class="-mr-0.5 inline-flex cursor-pointer items-center text-slate-400 transition-colors duration-[120ms] hover:text-rose-600"
                        :aria-label="`Retirer ${chip.label}`"
                        @click="remove(chip.value)"
                    >
                        <X :size="12" :stroke-width="2" />
                    </button>
                </span>
            </Badge>
        </div>

        <div class="relative">
            <input
                v-model="query"
                type="text"
                autocomplete="off"
                :placeholder="placeholder"
                class="w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none"
                @focus="open = true"
                @click="open = true"
                @keydown.esc="open = false"
                @keydown.enter.prevent="commitFreeEntry()"
                @blur="open = false"
            />
            <ul
                v-if="open && (filteredOptions.length > 0 || canCommitFreeEntry)"
                class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg"
            >
                <li
                    v-for="option in filteredOptions"
                    :key="option.value"
                    class="cursor-pointer px-3 py-1.5 text-sm text-slate-700 transition-colors duration-[100ms] hover:bg-slate-50"
                    @mousedown.prevent="add(option.value)"
                >
                    {{ option.label }}
                </li>
                <li
                    v-if="canCommitFreeEntry"
                    class="flex cursor-pointer items-center gap-1.5 border-t border-slate-100 px-3 py-1.5 text-sm text-slate-600 transition-colors duration-[100ms] hover:bg-slate-50"
                    @mousedown.prevent="commitFreeEntry()"
                >
                    <Plus :size="13" :stroke-width="1.75" />
                    Ajouter « {{ query.trim() }} »
                </li>
            </ul>
        </div>
    </div>
</template>
