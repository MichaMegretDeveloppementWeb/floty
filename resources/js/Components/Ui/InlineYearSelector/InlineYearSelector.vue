<script setup lang="ts">
/**
 * Sélecteur d'année compact en pill avec icône + label inline
 * (chantier UX-Loc suite). Remplace l'ancien pattern « FieldLabel
 * au-dessus + SelectInput » qui prenait 2 lignes et était trop discret.
 *
 * Utilisé sur Planning Index, Contracts Index, et autres pages liste
 * pour rester cohérent visuellement.
 */
import { CalendarDays } from 'lucide-vue-next';
import { useId } from 'vue';

withDefaults(
    defineProps<{
        options: { value: number; label: string }[];
        label?: string;
        disabled?: boolean;
        id?: string;
    }>(),
    {
        label: 'Exercice',
        disabled: false,
    },
);

const modelValue = defineModel<number>({ required: true });

const autoId = useId();
const inputId = (): string => autoId;
</script>

<template>
    <div
        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white py-1.5 pr-2 pl-3 shadow-sm transition-colors duration-[120ms] ease-out hover:border-slate-400"
    >
        <CalendarDays
            :size="14"
            :stroke-width="1.75"
            class="shrink-0 text-slate-500"
            aria-hidden="true"
        />
        <label
            :for="id ?? inputId()"
            class="text-sm font-medium whitespace-nowrap text-slate-700"
        >
            {{ label }}
        </label>
        <select
            :id="id ?? inputId()"
            v-model="modelValue"
            :disabled="disabled || options.length <= 1"
            class="cursor-pointer rounded-md bg-transparent py-0.5 pr-6 pl-2 text-sm font-semibold text-slate-900 focus-visible:bg-slate-50 focus-visible:outline-none disabled:cursor-not-allowed disabled:text-slate-400"
        >
            <option
                v-for="opt in options"
                :key="opt.value"
                :value="opt.value"
            >
                {{ opt.label }}
            </option>
        </select>
    </div>
</template>
