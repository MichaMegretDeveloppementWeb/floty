<script setup lang="ts">
import { computed } from 'vue';
import {
    densityClass,
    textContrastClass,
} from '@/Components/Features/Planning/Heatmap/utils/density';
import { isCellAfterExit } from '@/Components/Features/Planning/Heatmap/utils/exitedWeeks';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type Vehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;

const props = defineProps<{
    vehicle: Vehicle;
    fiscalYear: number;
}>();

const emit = defineEmits<{
    'cell-click': [payload: { vehicleId: number; week: number }];
}>();

const exitedWeekFlags = computed<boolean[]>(() =>
    props.vehicle.weeks.map((_, idx) =>
        isCellAfterExit(idx, props.vehicle.exitDate, props.fiscalYear),
    ),
);

const exitTooltip = computed<string | null>(() =>
    props.vehicle.exitDate === null
        ? null
        : `Véhicule retiré le ${formatDateFr(props.vehicle.exitDate)}`,
);
</script>

<template>
    <div
        class="flex h-[40px] items-center gap-[1px] border-t border-slate-100 first:border-t-0"
    >
        <button
            v-for="(days, weekIndex) in vehicle.weeks"
            :key="weekIndex"
            type="button"
            :class="[
                densityClass(days),
                textContrastClass(days),
                'flex h-7 w-5 items-center justify-center rounded-[3px] font-mono text-[9px] transition-opacity duration-[120ms] ease-out hover:opacity-70',
                exitedWeekFlags[weekIndex] && 'pointer-events-none opacity-30',
            ]"
            :aria-label="`Semaine ${weekIndex + 1} · ${vehicle.licensePlate} · ${days} jours utilisés`"
            :title="exitedWeekFlags[weekIndex] && exitTooltip
                ? exitTooltip
                : `S${weekIndex + 1} · ${days}j / 7`"
            :disabled="exitedWeekFlags[weekIndex]"
            @click="
                emit('cell-click', {
                    vehicleId: vehicle.id,
                    week: weekIndex + 1,
                })
            "
        >
            {{ days > 0 ? days : '' }}
        </button>
    </div>
</template>
