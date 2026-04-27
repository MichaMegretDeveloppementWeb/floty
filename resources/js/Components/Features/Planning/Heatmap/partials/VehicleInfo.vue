<script setup lang="ts">
type Vehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;

defineProps<{
    vehicle: Vehicle;
}>();
</script>

<template>
    <div
        class="flex h-[40px] items-center gap-2 border-t border-slate-100 first:border-t-0"
    >
        <span
            :class="[
                'rounded-[3px] px-1 py-0.5 font-mono text-[10px] font-semibold uppercase',
                vehicle.userType === 'VU'
                    ? 'bg-amber-50 text-amber-700'
                    : 'bg-slate-100 text-slate-600',
            ]"
        >
            {{ vehicle.userType }}
        </span>
        <div class="w-[200px] min-w-0">
            <p class="truncate font-mono text-xs font-medium text-slate-900">
                {{ vehicle.licensePlate }}
            </p>
            <p class="truncate text-[11px] text-slate-500">
                {{ vehicle.brand }} {{ vehicle.model }}
            </p>
        </div>
    </div>
</template>
