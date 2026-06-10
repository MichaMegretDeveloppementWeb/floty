<script setup lang="ts">
/**
 * Single-value text input with a CONTAINS-filtered suggestion dropdown
 * (same interaction as the nature / détails rows: @mousedown.prevent keeps
 * the focus, Escape / blur closes). The typed text always stays valid; the
 * suggestions are a shortcut, never a constraint. Markup mirrors TextInput
 * (FieldLabel + same input classes) so side-by-side fields stay aligned.
 */
import { computed, ref, useId } from 'vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';

const props = withDefaults(
    defineProps<{
        label: string;
        suggestions?: string[];
        placeholder?: string;
        maxLength?: number;
        error?: string;
    }>(),
    {
        suggestions: () => [],
        placeholder: '',
        maxLength: 120,
        error: undefined,
    },
);

const modelValue = defineModel<string>({ required: true });

const inputId = useId();
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

const inputStateClasses = computed<string>(() => {
    if (props.error) {
        return 'border-rose-600 text-rose-700 focus-visible:shadow-[0_0_0_3px_var(--color-rose-50)]';
    }

    return 'border-slate-200 text-slate-900 focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)]';
});

function select(value: string): void {
    modelValue.value = value;
    open.value = false;
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <FieldLabel :for="inputId">
            {{ label }}
        </FieldLabel>
        <div class="relative">
            <input
                :id="inputId"
                v-model="modelValue"
                :maxlength="maxLength"
                autocomplete="off"
                :placeholder="placeholder"
                :aria-invalid="error ? true : undefined"
                :class="[
                    'w-full rounded-lg border bg-white px-3 py-2 text-base leading-tight transition-colors duration-[120ms] ease-out focus:outline-none',
                    inputStateClasses,
                ]"
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
