<script setup lang="ts">
/**
 * Pills d'années cliquables pour filtre rapide « année complète »
 * (chantier N.1.fixes). 1 clic = `periodStart=YYYY-01-01`,
 * `periodEnd=YYYY-12-31`. La pill correspondant à l'année active
 * est highlighted.
 *
 * Scalable : pour les entreprises avec 20+ années d'historique, le
 * conteneur scrolle horizontalement (snap-x au repos pour un alignement
 * doux). Scrollbar masquée pour rester dense.
 */
import { computed } from 'vue';

const props = defineProps<{
    /** Plage continue [firstYear..currentYear]. */
    years: readonly number[];
    /** Année active (ou null si filtre custom / pas de filtre). */
    activeYear: number | null;
}>();

const emit = defineEmits<{
    select: [year: number];
}>();

// Plus récent à gauche : on inverse pour que la pill par défaut visible
// soit l'année courante (consultation la plus probable).
const reversedYears = computed<readonly number[]>(() =>
    [...props.years].reverse(),
);

function pillClass(year: number): string {
    const active = props.activeYear === year;
    if (active) {
        return 'snap-start shrink-0 rounded-full border border-blue-300 bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700 cursor-pointer transition-colors duration-[120ms]';
    }

    return 'snap-start shrink-0 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-medium text-slate-700 cursor-pointer transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50';
}
</script>

<template>
    <div class="flex items-center gap-3">
        <span
            class="shrink-0 text-xs font-medium tracking-wide text-slate-500 uppercase"
        >
            Année
        </span>
        <div
            class="flex flex-1 snap-x snap-mandatory gap-1.5 overflow-x-auto scrollbar-hide"
        >
            <button
                v-for="year in reversedYears"
                :key="year"
                type="button"
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
