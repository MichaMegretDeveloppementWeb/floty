<script setup lang="ts">
import InputError from '@/Components/Ui/InputError/InputError.vue';
import { Check } from 'lucide-vue-next';
import { computed, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        hint?: string;
        error?: string;
        disabled?: boolean;
        id?: string;
    }>(),
    {
        disabled: false,
    },
);

const modelValue = defineModel<boolean>({ required: true });

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
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            :for="inputId"
            :class="[
                'group inline-flex items-start gap-2.5',
                disabled ? 'cursor-not-allowed' : 'cursor-pointer',
            ]"
        >
            <span class="relative mt-0.5 inline-flex">
                <input
                    :id="inputId"
                    v-model="modelValue"
                    type="checkbox"
                    :disabled="disabled"
                    :aria-invalid="error ? true : undefined"
                    :aria-describedby="describedBy"
                    class="peer size-4 cursor-[inherit] appearance-none rounded-sm border border-slate-300 bg-white transition-colors duration-[120ms] ease-out checked:border-slate-900 checked:bg-slate-900 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)] focus-visible:outline-none disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 aria-invalid:border-rose-600"
                />
                <Check
                    :size="12"
                    :stroke-width="3"
                    class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-white opacity-0 peer-checked:opacity-100"
                    aria-hidden="true"
                />
            </span>
            <span class="flex flex-col gap-0.5">
                <span
                    :class="[
                        'text-base leading-tight',
                        disabled ? 'text-slate-400' : 'text-slate-900',
                    ]"
                >
                    {{ label }}
                </span>
                <span
                    v-if="hint"
                    :id="hintId"
                    class="text-xs text-slate-500"
                >
                    {{ hint }}
                </span>
            </span>
        </label>
        <InputError :id="errorId" :message="error" />
    </div>
</template>
