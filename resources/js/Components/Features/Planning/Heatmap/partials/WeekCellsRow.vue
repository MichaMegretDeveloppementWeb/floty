<script setup lang="ts">
import {
    densityClass,
    textContrastClass,
} from '@/Components/Features/Planning/Heatmap/utils/density';

type Vehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;

defineProps<{
    vehicle: Vehicle;
}>();

const emit = defineEmits<{
    'cell-click': [payload: { vehicleId: number; week: number }];
}>();
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
            ]"
            :aria-label="`Semaine ${weekIndex + 1} · ${vehicle.licensePlate} · ${days} jours utilisés`"
            :title="`S${weekIndex + 1} · ${days}j / 7`"
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
