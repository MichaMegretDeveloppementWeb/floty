<script setup lang="ts">
import type { HeatmapVehicleView } from '@/Components/Features/Planning/Heatmap/types';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    vehicleView: HeatmapVehicleView;
}>();
</script>

<template>
    <div
        class="flex h-[40px] items-center justify-end border-t border-slate-100 text-right first:border-t-0"
    >
        <div>
            <p class="font-mono text-[13px] font-medium text-slate-900 tabular-nums">
                {{ formatEur(vehicleView.summaryTax) }}
            </p>
            <p class="text-[12px] text-slate-500 tabular-nums">
                {{ vehicleView.summaryDays }} j
            </p>
        </div>
    </div>
</template>
