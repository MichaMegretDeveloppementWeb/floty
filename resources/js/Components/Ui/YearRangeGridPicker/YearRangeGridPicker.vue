<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    /** Borne minimale disponible (année la plus ancienne en BDD). */
    min: number;
    /** Borne maximale disponible (année la plus récente en BDD). */
    max: number;
}>();

const yearMin = defineModel<number | null>('yearMin', { required: true });
const yearMax = defineModel<number | null>('yearMax', { required: true });

const years = computed<number[]>(() => {
    const list: number[] = [];

    for (let y = props.max; y >= props.min; y--) {
        list.push(y);
    }

    return list;
});

function isMin(year: number): boolean {
    return yearMin.value === year && (yearMax.value === null || year !== yearMax.value);
}

function isMax(year: number): boolean {
    return yearMax.value === year && (yearMin.value === null || year !== yearMin.value);
}

function isSingle(year: number): boolean {
    return yearMin.value === year && yearMax.value === year;
}

function isInRange(year: number): boolean {
    if (yearMin.value === null || yearMax.value === null) {
        return false;
    }

    return year > yearMin.value && year < yearMax.value;
}

function onYearClick(year: number): void {
    // Cas 1 : aucune borne définie → set Min
    if (yearMin.value === null && yearMax.value === null) {
        yearMin.value = year;

        return;
    }

    // Cas 2 : Min seul défini → définir Max (ou re-set Min si année < Min)
    if (yearMin.value !== null && yearMax.value === null) {
        if (year >= yearMin.value) {
            yearMax.value = year;
        } else {
            // Année cliquée < Min → la nouvelle valeur devient Min,
            // l'ancien Min devient Max (range cohérent par construction).
            yearMax.value = yearMin.value;
            yearMin.value = year;
        }

        return;
    }

    // Cas 3 : Max seul défini → définir Min (ou re-set Max si année > Max)
    if (yearMin.value === null && yearMax.value !== null) {
        if (year <= yearMax.value) {
            yearMin.value = year;
        } else {
            yearMin.value = yearMax.value;
            yearMax.value = year;
        }

        return;
    }

    // Cas 4 : range complet → recommence (reset Max, set Min)
    yearMin.value = year;
    yearMax.value = null;
}

function clear(): void {
    yearMin.value = null;
    yearMax.value = null;
}

const hasSelection = computed<boolean>(
    () => yearMin.value !== null || yearMax.value !== null,
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            class="grid grid-cols-4 gap-1 rounded-lg border border-slate-200 bg-white p-2"
        >
            <button
                v-for="year in years"
                :key="year"
                type="button"
                :class="[
                    'rounded-md px-2 py-1.5 text-sm font-medium transition-colors duration-[120ms] ease-out',
                    isSingle(year) || isMin(year) || isMax(year)
                        ? 'bg-blue-600 text-white'
                        : isInRange(year)
                          ? 'bg-blue-100 text-blue-900'
                          : 'text-slate-700 hover:bg-slate-100',
                ]"
                @click="onYearClick(year)"
            >
                {{ year }}
            </button>
        </div>
        <div class="flex items-center justify-between text-xs text-slate-500">
            <span v-if="hasSelection">
                <template v-if="yearMin !== null && yearMax !== null">
                    {{
                        yearMin === yearMax
                            ? `Année ${yearMin}`
                            : `${yearMin} → ${yearMax}`
                    }}
                </template>
                <template v-else-if="yearMin !== null">
                    À partir de {{ yearMin }}
                </template>
                <template v-else-if="yearMax !== null">
                    Jusqu'à {{ yearMax }}
                </template>
            </span>
            <span v-else>Cliquez une année (puis une seconde pour une plage)</span>
            <button
                v-if="hasSelection"
                type="button"
                class="text-rose-600 hover:text-rose-700"
                @click="clear"
            >
                Effacer
            </button>
        </div>
    </div>
</template>
