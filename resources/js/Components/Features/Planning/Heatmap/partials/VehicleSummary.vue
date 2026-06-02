<script setup lang="ts">
import type { HeatmapVehicleView } from '@/Components/Features/Planning/Heatmap/types';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    vehicleView: HeatmapVehicleView;
    /** False when the year has no coded fiscal rules (shows a muted dash). */
    fiscalSupported: boolean;
}>();
</script>

<template>
    <div
        class="flex h-[56px] items-center justify-end border-t border-slate-100 text-right first:border-t-0"
    >
        <div>
            <p
                v-if="!fiscalSupported"
                class="font-mono text-[13px] font-medium text-slate-300 tabular-nums"
                title="Aucune règle fiscale codée pour cet exercice"
            >
                -
            </p>
            <p
                v-else-if="vehicleView.summaryTax !== null"
                class="font-mono text-[13px] font-medium text-slate-900 tabular-nums"
            >
                {{ formatEur(vehicleView.summaryTax) }}
            </p>
            <p v-else class="flex justify-end">
                <span
                    class="skeleton-shimmer inline-block h-3 w-16 rounded"
                    aria-label="Calcul en cours"
                ></span>
            </p>
            <p class="text-[12px] text-slate-500 tabular-nums">
                {{ vehicleView.summaryDays }} j
            </p>
        </div>
    </div>
</template>
