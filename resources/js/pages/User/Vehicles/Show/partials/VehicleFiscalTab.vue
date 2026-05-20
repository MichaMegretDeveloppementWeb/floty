<script setup lang="ts">
/**
 * Fiscal tab on the vehicle detail page: editorial header, year tabs,
 * hero KPI, stats row and VFC + calculation breakdown sections.
 * Year selection is driven by the shared `?year=` URL param.
 */
import { computed } from 'vue';
import { useVehicleFiscalSelectedYear } from '@/Composables/Vehicle/Show/useVehicleFiscalSelectedYear';
import { formatEur } from '@/Utils/format/formatEur';
import AppliedVfcCard from './fiscal/AppliedVfcCard.vue';
import FullYearTaxBreakdownPanel from './FullYearTaxBreakdownPanel.vue';

type Breakdown = App.Data.User.Vehicle.VehicleFullYearTaxBreakdownData;
type UsageStats = App.Data.User.Vehicle.VehicleUsageStatsData;

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    fiscalYearBreakdown: Breakdown;
    fiscalYear: number;
}>();

const { selectedYear, selectYear, loading } = useVehicleFiscalSelectedYear(
    props.fiscalYear,
);

const isCurrentYear = computed<boolean>(
    () => selectedYear.value === props.vehicle.kpiYear,
);

const isFutureYear = computed<boolean>(
    () => selectedYear.value > props.vehicle.kpiYear,
);

const statusLabel = computed<string>(() => {
    if (isFutureYear.value) {
        return 'Exercice à venir';
    }

    if (isCurrentYear.value) {
        return 'Exercice en cours';
    }

    return 'Exercice clos';
});

const statusDotClass = computed<string>(() => {
    if (isCurrentYear.value) {
        return 'bg-amber-500';
    }

    if (isFutureYear.value) {
        return 'bg-slate-400';
    }

    return 'bg-emerald-500';
});

const segmentsCount = computed<number>(
    () => props.fiscalYearBreakdown.taxSegments.length,
);

const metaLine = computed<string>(() => {
    const n = segmentsCount.value;

    if (n === 0) {
        return 'Aucune VFC effective sur cette année · le calcul utilise des valeurs par défaut.';
    }

    if (n === 1) {
        return 'Taxe théorique pour 100 % d\'utilisation · 1 version VFC sur l\'année.';
    }

    return `Taxe théorique pour 100 % d'utilisation · ${n} versions VFC se succèdent sur l'année.`;
});

const yearsDescending = computed<readonly number[]>(
    () => [...props.vehicle.yearScope.availableYears].sort((a, b) => b - a),
);

/**
 * Per-tax totals aggregated client-side across all VFC segments. The
 * year-level total may differ slightly because rounding (R-2024-003) is
 * applied once at year level, but the divergence is acceptable for the
 * indicative percentage split shown here.
 */
const totalCo2 = computed<number>(
    () => props.fiscalYearBreakdown.taxSegments.reduce((acc, s) => acc + s.co2Due, 0),
);

const totalPollutants = computed<number>(
    () => props.fiscalYearBreakdown.taxSegments.reduce((acc, s) => acc + s.pollutantsDue, 0),
);

const totalCo2PlusPollutants = computed<number>(
    () => totalCo2.value + totalPollutants.value,
);

const co2Percent = computed<number>(() => {
    if (totalCo2PlusPollutants.value <= 0) {
        return 0;
    }

    return Math.round((totalCo2.value / totalCo2PlusPollutants.value) * 100);
});

const pollutantsPercent = computed<number>(() => 100 - co2Percent.value);

const hasTax = computed<boolean>(() => props.fiscalYearBreakdown.total > 0);

// The breakdown panel only reads `fiscalYear` and `fullYearTaxBreakdown`,
// so we synthesise a minimal stats-like object for it.
const statsLike = computed<UsageStats>(() => ({
    ...props.vehicle.usageStats,
    fiscalYear: props.fiscalYear,
    fullYearTaxBreakdown: props.fiscalYearBreakdown,
}));
</script>

<template>
    <div class="flex flex-col">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            Fiscalité · Exercice {{ selectedYear }}
        </p>
        <div class="mb-1 flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
            <h2 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                Taxe pleine {{ selectedYear }}
            </h2>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
                <span
                    :class="['inline-block size-1.5 rounded-full', statusDotClass]"
                    aria-hidden="true"
                />
                {{ statusLabel }}
            </span>
        </div>
        <p class="mb-7 text-sm text-slate-500">
            {{ metaLine }}
        </p>

        <nav
            v-if="props.vehicle.yearScope.availableYears.length > 0"
            class="mb-10 flex gap-6 border-b border-slate-100"
            aria-label="Sélection de l'exercice"
        >
            <button
                v-for="year in yearsDescending"
                :key="year"
                type="button"
                :disabled="loading"
                :class="[
                    '-mb-px cursor-pointer border-b-2 pb-3 text-sm font-medium transition-colors duration-[120ms]',
                    selectedYear === year
                        ? 'border-slate-900 text-slate-900'
                        : 'border-transparent text-slate-500 hover:text-slate-900',
                    loading && 'cursor-wait opacity-60',
                ]"
                @click="selectYear(year)"
            >
                {{ year }}
            </button>
        </nav>

        <div :class="{ 'opacity-60 transition-opacity duration-150': loading }">
            <div class="mb-10 flex flex-col gap-1.5">
                <p class="font-mono text-[28px] sm:text-[36px] font-medium tracking-[-0.02em] tabular-nums leading-none text-slate-900">
                    {{ formatEur(fiscalYearBreakdown.total) }}
                </p>
                <p class="text-sm text-slate-500">
                    Théorique pour 100 % d'utilisation
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-5 sm:gap-x-0 gap-y-6 sm:gap-y-0 border-y border-slate-100 py-6">
                <div class="sm:px-6 sm:first:pl-0 sm:last:pr-0 sm:not-last:border-r sm:border-slate-100">
                    <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Taxe CO₂
                    </p>
                    <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                        {{ formatEur(totalCo2) }}
                    </p>
                    <div v-if="hasTax" class="mt-2 flex items-center gap-2">
                        <div class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full bg-slate-800 transition-[width] duration-300"
                                :style="{ width: `${co2Percent}%` }"
                            />
                        </div>
                        <span class="font-mono text-[11px] tabular-nums text-slate-500">
                            {{ co2Percent }} %
                        </span>
                    </div>
                </div>
                <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
                    <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Taxe polluants
                    </p>
                    <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                        {{ formatEur(totalPollutants) }}
                    </p>
                    <div v-if="hasTax" class="mt-2 flex items-center gap-2">
                        <div class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full bg-slate-800 transition-[width] duration-300"
                                :style="{ width: `${pollutantsPercent}%` }"
                            />
                        </div>
                        <span class="font-mono text-[11px] tabular-nums text-slate-500">
                            {{ pollutantsPercent }} %
                        </span>
                    </div>
                </div>
                <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
                    <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Jours d'année
                    </p>
                    <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                        {{ fiscalYearBreakdown.daysInYear }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">
                        <template v-if="fiscalYearBreakdown.daysInYear === 366">
                            année bissextile
                        </template>
                        <template v-else>
                            année standard
                        </template>
                    </p>
                </div>
                <div class="sm:px-6 sm:last:pr-0">
                    <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Versions VFC
                    </p>
                    <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                        {{ segmentsCount }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">
                        <template v-if="segmentsCount === 0">
                            aucune sur l'année
                        </template>
                        <template v-else-if="segmentsCount === 1">
                            version unique
                        </template>
                        <template v-else>
                            versions successives
                        </template>
                    </p>
                </div>
            </div>

            <section class="mt-12 flex flex-col gap-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Caractéristiques VFC
                </p>
                <AppliedVfcCard :segments="props.fiscalYearBreakdown.taxSegments" unwrapped />
            </section>

            <section class="mt-12 flex flex-col gap-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Détail du calcul
                </p>
                <FullYearTaxBreakdownPanel :stats="statsLike" unwrapped />
            </section>
        </div>
    </div>
</template>
