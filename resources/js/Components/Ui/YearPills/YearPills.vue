<script setup lang="ts">
/**
 * Pills d'années cliquables pour sélecteur rapide d'exercice. Conçu pour
 * les onglets fiscaux (entreprise, véhicule) où un utilisateur navigue
 * entre exercices avec un partial reload Inertia.
 *
 * Plus récent à gauche (consultation la plus probable). Scrollable
 * horizontalement pour les entités avec 20+ exercices d'historique.
 */
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Plage continue [firstYear..currentYear]. */
        years: readonly number[];
        /** Année active (ou null si filtre custom / pas de filtre). */
        activeYear: number | null;
        /** Désactive l'interaction pendant le partial reload Inertia. */
        loading?: boolean;
        /** Libellé optionnel à gauche des pills. Défaut « Année ». */
        label?: string;
    }>(),
    { loading: false, label: 'Année' },
);

const emit = defineEmits<{
    select: [year: number];
}>();

const reversedYears = computed<readonly number[]>(() =>
    [...props.years].reverse(),
);

function pillClass(year: number): string {
    const active = props.activeYear === year;
    const disabled = props.loading;

    const base = 'snap-start shrink-0 rounded-full border px-3 py-1 text-sm transition-colors duration-[120ms]';
    const cursor = disabled ? 'cursor-wait opacity-60' : 'cursor-pointer';

    if (active) {
        return `${base} ${cursor} border-blue-300 bg-blue-50 font-semibold text-blue-700`;
    }

    return `${base} ${cursor} border-slate-200 bg-white font-medium text-slate-700 ${disabled ? '' : 'hover:border-slate-300 hover:bg-slate-50'}`;
}
</script>

<template>
    <div class="flex items-center gap-3">
        <span
            class="shrink-0 text-xs font-medium tracking-wide text-slate-500 uppercase"
        >
            {{ label }}
        </span>
        <div
            class="flex flex-1 snap-x snap-mandatory gap-1.5 overflow-x-auto scrollbar-hide"
        >
            <button
                v-for="year in reversedYears"
                :key="year"
                type="button"
                :disabled="loading"
                :class="pillClass(year)"
                @click="emit('select', year)"
            >
                {{ year }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-hide {
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
