<script setup lang="ts">
/**
 * Single-value text input with a CONTAINS-filtered suggestion dropdown
 * (same interaction as the nature / détails rows: @mousedown.prevent keeps
 * the focus, Escape / blur closes). The typed text always stays valid; the
 * suggestions are a shortcut, never a constraint.
 */
import { computed, ref } from 'vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';

const props = withDefaults(
    defineProps<{
        label: string;
        suggestions?: string[];
        placeholder?: string;
        hint?: string;
        maxLength?: number;
        error?: string;
    }>(),
    {
        suggestions: () => [],
        placeholder: '',
        hint: undefined,
        maxLength: 120,
        error: undefined,
    },
);

const modelValue = defineModel<string>({ required: true });

const open = ref<boolean>(false);

const filteredSuggestions = computed<string[]>(() => {
    const typed = modelValue.value.trim().toLowerCase();

    return props.suggestions.filter((suggestion) => {
        const key = suggestion.trim().toLowerCase();

        if (key === '' || key === typed) {
            return false;
        }

        return typed === '' || key.includes(typed);
    });
});

function select(value: string): void {
    modelValue.value = value;
    open.value = false;
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium text-slate-500">
            {{ label }}
        </label>
        <p v-if="hint" class="text-xs text-slate-500">
            {{ hint }}
        </p>
        <div class="relative">
            <input
                v-model="modelValue"
                :maxlength="maxLength"
                autocomplete="off"
                :placeholder="placeholder"
                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none"
                @focus="open = true"
                @click="open = true"
                @keydown.esc="open = false"
                @blur="open = false"
            />
            <ul
                v-if="open && filteredSuggestions.length > 0"
                class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg"
            >
                <li
                    v-for="suggestion in filteredSuggestions"
                    :key="suggestion"
                    class="cursor-pointer px-3 py-1.5 text-sm text-slate-700 transition-colors duration-[100ms] hover:bg-slate-50"
                    @mousedown.prevent="select(suggestion)"
                >
                    {{ suggestion }}
                </li>
            </ul>
        </div>
        <InputError v-if="error" :message="error" />
    </div>
</template>
