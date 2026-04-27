<script setup lang="ts">
import { computed, useId, useSlots } from 'vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';

type InputType = 'text' | 'email' | 'tel' | 'url' | 'password';

const props = withDefaults(
    defineProps<{
        label?: string;
        type?: InputType;
        placeholder?: string;
        hint?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        autocomplete?: string;
        id?: string;
        mono?: boolean;
    }>(),
    {
        type: 'text',
        disabled: false,
        required: false,
        mono: false,
    },
);

const modelValue = defineModel<string>({ required: true });

const autoId = useId();
const inputId = computed<string>(() => props.id ?? autoId);
const errorId = computed<string>(() => `${inputId.value}-error`);
const hintId = computed<string>(() => `${inputId.value}-hint`);

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

const slots = useSlots();
const hasLeadingIcon = computed<boolean>(() => !!slots['icon-left']);
const hasTrailingAdornment = computed<boolean>(
    () => !!slots['adornment-right'],
);

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
            <span
                v-if="hasLeadingIcon"
                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            >
                <slot name="icon-left" />
            </span>
            <input
                :id="inputId"
                v-model="modelValue"
                :type="type"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                :autocomplete="autocomplete"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                :class="[
                    'w-full rounded-lg border bg-white px-3 py-2 text-base leading-tight transition-colors duration-[120ms] ease-out focus:outline-none',
                    mono && 'font-mono',
                    hasLeadingIcon && 'pl-9',
                    hasTrailingAdornment && 'pr-20',
                    inputStateClasses,
                ]"
            />
            <span
                v-if="hasTrailingAdornment"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 flex items-center gap-1"
            >
                <slot name="adornment-right" />
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
