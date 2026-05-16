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
            <!-- Coût servi en différé · skeleton tant que la 2ᵉ RTT
                 Inertia::defer n'a pas répondu pour ce véhicule (chantier
                 perf 2026-05-16). Bbox alignée droite cohérente avec la
                 valeur finale (h-3, w-16). -->
            <p
                v-if="vehicleView.summaryTax !== null"
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
