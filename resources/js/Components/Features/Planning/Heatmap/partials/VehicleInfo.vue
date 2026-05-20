<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HeatmapVehicleView } from '@/Components/Features/Planning/Heatmap/types';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    vehicleView: HeatmapVehicleView;
}>();

/** Fiscal costs are deferred; the inline skeleton holds the place until response. */
const fiscalLoaded = computed<boolean>(
    () => props.vehicleView.fullYearTax !== null && props.vehicleView.dailyTaxRate !== null,
);
const tooltipTitle = computed<string>(() =>
    fiscalLoaded.value
        ? `Taxe annuelle théorique ${formatEur(props.vehicleView.fullYearTax!, 0)} · prorata ${formatEur(props.vehicleView.dailyTaxRate!, 2)}/jour`
        : 'Calcul en cours',
);

/** Daily/weekly/monthly pricings (eager). Display a muted dash when unset. */
const hasPricing = computed<boolean>(
    () =>
        props.vehicleView.dailyRateCents !== null
        || props.vehicleView.weeklyRateCents !== null
        || props.vehicleView.monthlyRateCents !== null,
);
const formatRate = (cents: number | null): string =>
    cents === null ? '-' : formatEur(cents / 100, 0);
</script>

<template>
    <div class="flex h-[56px] flex-col justify-center gap-0">
        <div class="flex items-center gap-2">
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
                class="group flex w-[120px] min-w-0 flex-col cursor-pointer"
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
            <!-- Fixed 80px width avoids layout shift when the deferred value arrives. -->
            <div
                class="flex w-20 shrink-0 flex-col items-end leading-tight"
                :title="tooltipTitle"
            >
                <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
                    Taxe pleine
                </span>
                <span
                    v-if="fiscalLoaded"
                    class="font-mono text-[11px] text-slate-500 tabular-nums"
                >
                    {{ formatEur(vehicleView.fullYearTax!, 0) }}
                </span>
                <span
                    v-else
                    class="skeleton-shimmer mt-0.5 inline-block h-3 w-14 rounded"
                    aria-label="Calcul en cours"
                ></span>
            </div>
        </div>
        <div
            class="flex items-center gap-2 pl-[10px] font-mono text-[10px] text-slate-500 tabular-nums"
            title="Tarifs jour · semaine · mois"
        >
            <span>Loyer</span>

            <span v-if="hasPricing" class="truncate">
                <span class="text-slate-400">J</span> {{ formatRate(vehicleView.dailyRateCents) }}
                <span class="mx-1 text-slate-300">·</span>
                <span class="text-slate-400">S</span> {{ formatRate(vehicleView.weeklyRateCents) }}
                <span class="mx-1 text-slate-300">·</span>
                <span class="text-slate-400">M</span> {{ formatRate(vehicleView.monthlyRateCents) }}
            </span>
            <span v-else class="text-slate-300">-</span>
        </div>
    </div>
</template>
