<script setup lang="ts">
/**
 * Sélecteur de couleur entreprise (Phase 13 D5.10.M).
 *
 * Affiche les 8 couleurs CompanyColor sous forme de pastilles cliquables
 * (les mêmes classes `bg-company-{color}` que `<CompanyTag>`). L'utilisateur
 * voit immédiatement la teinte qu'il sélectionne · plus pédagogique qu'un
 * SelectInput texte.
 *
 * Pattern accessibility · `role="radiogroup"` + `role="radio"` sur chaque
 * pastille, navigation Tab + Space/Enter pour sélectionner.
 */
import { Check } from 'lucide-vue-next';
import { computed, useId } from 'vue';

type ColorOption = App.Data.User.Company.CompanyColorOptionData;

const props = withDefaults(
    defineProps<{
        /**
         * Valeur de l'enum CompanyColor (string slug `indigo`, `emerald`,
         * etc.). Typé `string` pour compat avec les form shapes
         * `useForm()` qui stockent les valeurs en `string`.
         */
        modelValue: string;
        options: readonly ColorOption[];
        label?: string;
        error?: string;
        required?: boolean;
    }>(),
    {
        label: undefined,
        error: undefined,
        required: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const labelId = useId();

function dotClassFor(value: string): string {
    switch (value) {
        case 'indigo':
            return 'bg-company-indigo';
        case 'emerald':
            return 'bg-company-emerald';
        case 'amber':
            return 'bg-company-amber';
        case 'rose':
            return 'bg-company-rose';
        case 'violet':
            return 'bg-company-violet';
        case 'teal':
            return 'bg-company-teal';
        case 'orange':
            return 'bg-company-orange';
        case 'cyan':
            return 'bg-company-cyan';
        default:
            return 'bg-slate-400';
    }
}

const ariaLabelledBy = computed<string | undefined>(
    () => (props.label ? labelId : undefined),
);

function selectColor(value: string): void {
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span
            v-if="label"
            :id="labelId"
            class="text-md font-medium text-slate-600"
        >
            {{ label }}
            <span
                v-if="required"
                aria-hidden="true"
                class="ml-0.5 text-rose-600"
            >
                *
            </span>
        </span>
        <div
            role="radiogroup"
            :aria-labelledby="ariaLabelledBy"
            class="flex flex-wrap gap-2.5"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                role="radio"
                :aria-checked="option.value === modelValue"
                :aria-label="option.label"
                :title="option.label"
                :class="[
                    'flex h-9 w-9 cursor-pointer items-center justify-center rounded-full transition-shadow duration-[120ms] focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2',
                    dotClassFor(option.value),
                    option.value === modelValue
                        ? 'ring-2 ring-slate-900 ring-offset-2'
                        : 'hover:ring-2 hover:ring-slate-300 hover:ring-offset-2',
                ]"
                @click="selectColor(option.value)"
            >
                <Check
                    v-if="option.value === modelValue"
                    :size="16"
                    :stroke-width="3"
                    class="text-white"
                    aria-hidden="true"
                />
            </button>
        </div>
        <p v-if="error" class="text-xs text-rose-600">
            {{ error }}
        </p>
    </div>
</template>
