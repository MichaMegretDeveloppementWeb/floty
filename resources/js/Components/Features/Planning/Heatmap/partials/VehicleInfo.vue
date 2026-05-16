<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { HeatmapVehicleView } from '@/Components/Features/Planning/Heatmap/types';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    vehicleView: HeatmapVehicleView;
}>();
</script>

<template>
    <div
        class="flex h-[40px] items-center gap-2 border-t border-slate-100 first:border-t-0"
    >
        <span
            :class="[
                'rounded-[3px] px-1 py-0.5 font-mono text-[10px] font-semibold uppercase',
                vehicleView.userType === 'VU'
                    ? 'bg-amber-50 text-amber-700'
                    : 'bg-slate-100 text-slate-600',
            ]"
        >
            {{ vehicleView.userType }}
        </span>
        <Link
            :href="vehiclesShowRoute.url({ vehicle: vehicleView.id })"
            class="group flex w-[160px] min-w-0 flex-col cursor-pointer"
        >
            <div class="flex items-center gap-1.5">
                <p class="truncate font-mono text-xs font-medium text-slate-900 group-hover:underline">
                    {{ vehicleView.licensePlate }}
                </p>
                <span
                    v-if="vehicleView.exitDate !== null"
                    class="shrink-0 rounded-[3px] bg-slate-200 px-1 py-0.5 text-[9px] font-semibold tracking-wide text-slate-700 uppercase"
                >
                    Retiré
                </span>
            </div>
            <p class="truncate text-[11px] text-slate-500 group-hover:text-slate-700">
                {{ vehicleView.brand }} {{ vehicleView.model }}
            </p>
        </Link>
        <div
            class="flex shrink-0 flex-col items-end leading-tight"
            :title="`Taxe annuelle théorique ${formatEur(vehicleView.fullYearTax, 0)} · prorata ${formatEur(vehicleView.dailyTaxRate, 2)}/jour`"
        >
            <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
                Taxe pleine
            </span>
            <span class="font-mono text-[11px] text-slate-500 tabular-nums">
                {{ formatEur(vehicleView.fullYearTax, 0) }}
            </span>
        </div>
    </div>
</template>
