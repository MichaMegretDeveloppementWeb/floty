<script setup lang="ts">
import { computed } from 'vue';
import type { HeatmapVehicleView } from '@/Components/Features/Planning/Heatmap/types';
import {
    densityClass,
    textContrastClass,
} from '@/Components/Features/Planning/Heatmap/utils/density';
import { isCellAfterExit } from '@/Components/Features/Planning/Heatmap/utils/exitedWeeks';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    vehicleView: HeatmapVehicleView;
    fiscalYear: number;
}>();

const emit = defineEmits<{
    'cell-click': [payload: { vehicleId: number; week: number }];
}>();

const exitedWeekFlags = computed<boolean[]>(() =>
    props.vehicleView.weeksForCount.map((_, idx) =>
        isCellAfterExit(idx, props.vehicleView.exitDate, props.fiscalYear),
    ),
);

const exitTooltip = computed<string | null>(() =>
    props.vehicleView.exitDate === null
        ? null
        : `Véhicule retiré le ${formatDateFr(props.vehicleView.exitDate)}`,
);

// ADR-0019 D5 : bordure rouge sur les cellules de semaines portant
// au moins un jour d'indispo (avec ou sans contrat sur la même
// semaine), pour rendre visible la cohabitation indispo↔contrat
// désormais autorisée.
const unavailabilityWeekFlags = computed<boolean[]>(() => {
    const set = new Set(props.vehicleView.weeksWithUnavailability);

    return props.vehicleView.weeksForCount.map((_, idx) => set.has(idx + 1));
});
</script>

<template>
    <!-- SC18 (2026-05-18) · grid 53 colonnes identiques (au lieu de flex
         grow basis-0 qui produisait des décalages sub-pixel entre rows).
         minmax(14px, 1fr) · cellule min 14px (sinon scroll H sur viewport
         étroit), sinon partage équitable de l'espace disponible. Toutes
         les rows utilisent la même grille → alignement parfait colonne
         par colonne. -->
    <div
        class="grid h-[56px] items-center gap-[1px] border-t border-slate-100 first:border-t-0"
        :style="{ gridTemplateColumns: `repeat(${vehicleView.weeksForCount.length}, minmax(14px, 1fr))` }"
    >
        <button
            v-for="(daysCount, weekIndex) in vehicleView.weeksForCount"
            :key="weekIndex"
            type="button"
            :class="[
                densityClass(vehicleView.weeksForColor[weekIndex] ?? 0),
                textContrastClass(vehicleView.weeksForColor[weekIndex] ?? 0),
                'flex h-7 min-w-0 items-center justify-center rounded-[3px] font-mono text-[9px] transition-opacity duration-[120ms] ease-out hover:opacity-70',
                exitedWeekFlags[weekIndex] && 'pointer-events-none opacity-30',
                unavailabilityWeekFlags[weekIndex] && 'ring-1 ring-rose-500 ring-inset',
            ]"
            :aria-label="`Semaine ${weekIndex + 1} · ${vehicleView.licensePlate} · ${daysCount} jours utilisés${unavailabilityWeekFlags[weekIndex] ? ' (indisponibilité présente)' : ''}`"
            :title="exitedWeekFlags[weekIndex] && exitTooltip
                ? exitTooltip
                : `S${weekIndex + 1} · ${daysCount}j / 7${unavailabilityWeekFlags[weekIndex] ? ' · indisponibilité présente' : ''}`"
            :disabled="exitedWeekFlags[weekIndex]"
            @click="
                emit('cell-click', {
                    vehicleId: vehicleView.id,
                    week: weekIndex + 1,
                })
            "
        >
            {{ daysCount > 0 ? daysCount : '' }}
        </button>
    </div>
</template>
