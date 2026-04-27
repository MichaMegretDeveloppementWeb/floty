<script setup lang="ts">
import { formatEur } from '@/Utils/format/formatEur';

type Vehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;

defineProps<{
    vehicle: Vehicle;
}>();
</script>

<template>
    <div
        class="flex h-[40px] items-center justify-end border-t border-slate-100 text-right first:border-t-0"
    >
        <div>
            <p class="font-mono text-xs font-medium text-slate-900">
                {{ formatEur(vehicle.annualTaxDue) }}
            </p>
            <p class="text-[11px] text-slate-500">
                {{ vehicle.daysTotal }} j
            </p>
        </div>
    </div>
</template>
