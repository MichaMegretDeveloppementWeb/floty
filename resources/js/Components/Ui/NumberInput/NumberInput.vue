<script setup lang="ts">
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import { computed, useId, useSlots } from 'vue';

const props = withDefaults(
    defineProps<{
        label?: string;
        placeholder?: string;
        hint?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        min?: number;
        max?: number;
        step?: number | 'any';
        id?: string;
    }>(),
    {
        disabled: false,
        required: false,
    },
);

const modelValue = defineModel<number | null>({ required: true });

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

const slots = useSlots();
const hasUnit = computed<boolean>(() => !!slots.unit);

const inputStateClasses = computed<string>(() => {
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
            <input
                :id="inputId"
                v-model.number="modelValue"
                type="number"
                inputmode="decimal"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                :min="min"
                :max="max"
                :step="step"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                :class="[
                    'w-full rounded-lg border bg-white px-3 py-2 font-mono text-base leading-tight tabular-nums transition-colors duration-[120ms] ease-out focus:outline-none',
                    hasUnit && 'pr-12',
                    inputStateClasses,
                ]"
            />
            <span
                v-if="hasUnit"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 font-mono text-sm text-slate-500"
            >
                <slot name="unit" />
            </span>
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
