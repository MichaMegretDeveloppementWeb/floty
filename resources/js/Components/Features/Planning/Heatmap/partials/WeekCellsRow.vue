<script setup lang="ts">
import { computed } from 'vue';
import type { HeatmapVehicleView } from '@/Components/Features/Planning/Heatmap/types';
import {
    densityClass,
    densityRingClass,
    textContrastClass,
} from '@/Components/Features/Planning/Heatmap/utils/density';
import { isCellAfterExit } from '@/Components/Features/Planning/Heatmap/utils/exitedWeeks';
import { CELL_WIDTH_PX } from '@/Utils/Date/isoWeeks';
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

// Red ring on weeks carrying at least one unavailability day.
const unavailabilityWeekFlags = computed<boolean[]>(() => {
    const set = new Set(props.vehicleView.weeksWithUnavailability);

    return props.vehicleView.weeksForCount.map((_, idx) => set.has(idx + 1));
});

// Blue inset ring on 1-2 day cells; suppressed when the unavailability ring is shown.
const lowDensityRingClasses = computed<string[]>(() =>
    props.vehicleView.weeksForCount.map((_, idx) => {
        if (unavailabilityWeekFlags.value[idx]) {
            return '';
        }

        return densityRingClass(props.vehicleView.weeksForColor[idx] ?? 0);
    }),
);
</script>

<template>
    <div
        class="grid h-[56px] items-center gap-[1px] border-t border-slate-100 first:border-t-0"
        :style="{ gridTemplateColumns: `repeat(${vehicleView.weeksForCount.length}, ${CELL_WIDTH_PX}px)` }"
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
                lowDensityRingClasses[weekIndex],
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
