<script setup lang="ts">
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import { ChevronDown } from 'lucide-vue-next';
import { computed, useId } from 'vue';

type SelectOption = {
    value: string | number;
    label: string;
};

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
    }>(),
    {
        disabled: false,
        required: false,
    },
);

const modelValue = defineModel<string | number | null>({ required: true });

const autoId = useId();
const inputId = computed<string>(() => props.id ?? autoId);
const errorId = computed<string>(() => `${inputId.value}-error`);
const hintId = computed<string>(() => `${inputId.value}-hint`);

const describedBy = computed<string | undefined>(() => {
    const ids: string[] = [];
    if (props.hint) ids.push(hintId.value);
    if (props.error) ids.push(errorId.value);
    return ids.length ? ids.join(' ') : undefined;
});

const selectStateClasses = computed<string>(() => {
    if (props.error) {
        return 'border-rose-600 text-rose-700 focus-visible:shadow-[0_0_0_3px_var(--color-rose-50)]';
    }
    if (props.disabled) {
        return 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed';
    }
    return 'border-slate-200 text-slate-900 focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)]';
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <FieldLabel v-if="label" :for="inputId" :required="required">
            {{ label }}
        </FieldLabel>
        <div class="relative">
            <select
                :id="inputId"
                v-model="modelValue"
                :disabled="disabled"
                :required="required"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                :class="[
                    'w-full appearance-none rounded-lg border bg-white pr-9 pl-3 py-2 text-base leading-tight transition-colors duration-[120ms] ease-out focus:outline-none',
                    selectStateClasses,
                ]"
            >
                <option v-if="placeholder" value="" disabled>
                    {{ placeholder }}
                </option>
                <option
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <ChevronDown
                :size="14"
                :stroke-width="1.75"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            />
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
