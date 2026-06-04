<script setup lang="ts">
/**
 * Annual planning heatmap: vehicles x 52 weeks with density coloring.
 * Three synchronized scroll panes (left vehicle info, center cells, right
 * summary). Accepts both global and per-company vehicle DTOs and normalises
 * them via the vehicleViews computed before passing to partials.
 * Emits `cell-click` with { vehicleId, week }.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    leftCalcForDayOffset,
    monthLabelPositionsForYear,
    widthCalcBetweenDayOffsets,
} from '@/Components/Features/Planning/Heatmap/utils/monthLabelPositions';
import { weekBackgroundsForYear } from '@/Components/Features/Planning/Heatmap/utils/weekBackgrounds';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import { CELLS_PER_YEAR, CELL_WIDTH_PX, GRID_CONTENT_WIDTH_PX } from '@/Utils/date/isoWeeks';
import { formatEur } from '@/Utils/format/formatEur';
import HeatmapLegend from './partials/HeatmapLegend.vue';
import HeatmapSummary from './partials/HeatmapSummary.vue';
import VehicleInfo from './partials/VehicleInfo.vue';
import VehicleSummary from './partials/VehicleSummary.vue';
import WeekCellsRow from './partials/WeekCellsRow.vue';
import type {
    HeatmapFullYearCosts,
    HeatmapMonthlyRentals,
    HeatmapRealCosts,
    HeatmapVehicleView,
} from './types';

type OverviewVehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;
type CompanyVehicle = App.Data.User.Planning.PlanningHeatmapCompanyVehicleData;

const props = defineProps<{
    vehicles: OverviewVehicle[] | CompanyVehicle[];
    fiscalYear: number;
    /** Theoretical full-year costs, deferred ("fast" group, cached). */
    fullYearCosts?: HeatmapFullYearCosts;
    /** Actual annual tax due, deferred ("slow" group, not cached). */
    realCosts?: HeatmapRealCosts;
    /** Monthly cumulative rental net, deferred ("rentals" group). */
    monthlyRentals?: HeatmapMonthlyRentals;
    /**
     * False when the selected year has no coded fiscal rules · tax columns
     * render a "Pas de règles fiscales" placeholder instead of misleading
     * 0 € amounts. Rental figures stay unaffected.
     */
    fiscalSupported: boolean;
    /** Current direction of the license-plate sort on the vehicle column. */
    sortDirection: 'asc' | 'desc';
}>();

defineEmits<{
    'cell-click': [payload: { vehicleId: number; week: number }];
    'sort-toggle': [];
}>();

const MONTH_NAMES = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

// 12 monthly label positions with exact day offsets for pixel-perfect alignment.
const monthLabels = computed(() =>
    monthLabelPositionsForYear(props.fiscalYear).map((p) => ({
        ...p,
        name: MONTH_NAMES[p.monthIdx - 1]!,
        leftCalc: leftCalcForDayOffset(p.startDayOffset),
        widthCalc: widthCalcBetweenDayOffsets(p.startDayOffset, p.endDayOffset),
    })),
);

// 53 cell backgrounds for the alternating month-parity overlay (with hard-stop on month boundaries).
const weekBackgrounds = computed(() => weekBackgroundsForYear(props.fiscalYear));

// 53-column grid with a fixed CELL_WIDTH_PX, shared between overlay and WeekCellsRow.
const gridColumns = `repeat(${CELLS_PER_YEAR}, ${CELL_WIDTH_PX}px)`;
const gridContentWidth = GRID_CONTENT_WIDTH_PX;

function isCompanyVariant(v: OverviewVehicle | CompanyVehicle): v is CompanyVehicle {
    return 'weeksGlobal' in v;
}

const vehicleViews = computed<HeatmapVehicleView[]>(() =>
    props.vehicles.map((v) => {
        const fullCost = props.fullYearCosts?.[v.id] ?? null;
        const realCost = props.realCosts?.[v.id] ?? null;
        const summaryTax = realCost?.annualTaxDue ?? null;
        const fullYearTax = fullCost?.fullYearTax ?? null;
        const dailyTaxRate = fullCost?.dailyTaxRate ?? null;

        if (isCompanyVariant(v)) {
            return {
                id: v.id,
                licensePlate: v.licensePlate,
                brand: v.brand,
                model: v.model,
                userType: v.userType,
                energy: v.energy,
                co2Method: v.co2Method,
                co2Value: v.co2Value,
                taxableHorsepower: v.taxableHorsepower,
                weeksForColor: v.weeksGlobal,
                weeksForCount: v.weeksForCompany,
                summaryDays: v.daysTotalForCompany,
                summaryTax,
                exitDate: v.exitDate,
                weeksWithVehicleEvent: v.weeksWithVehicleEvent,
                fullYearTax,
                dailyTaxRate,
                dailyRateCents: v.dailyRateCents,
                weeklyRateCents: v.weeklyRateCents,
                monthlyRateCents: v.monthlyRateCents,
            };
        }

        return {
            id: v.id,
            licensePlate: v.licensePlate,
            brand: v.brand,
            model: v.model,
            userType: v.userType,
            energy: v.energy,
            co2Method: v.co2Method,
            co2Value: v.co2Value,
            taxableHorsepower: v.taxableHorsepower,
            weeksForColor: v.weeks,
            weeksForCount: v.weeks,
            summaryDays: v.daysTotal,
            summaryTax,
            exitDate: v.exitDate,
            weeksWithVehicleEvent: v.weeksWithVehicleEvent,
            fullYearTax,
            dailyTaxRate,
            dailyRateCents: v.dailyRateCents,
            weeklyRateCents: v.weeklyRateCents,
            monthlyRateCents: v.monthlyRateCents,
        };
    }),
);

// Null while any row still awaits its costs; HeatmapSummary renders a skeleton.
const totalAnnualTax = computed((): number | null => {
    if (vehicleViews.value.some((v) => v.summaryTax === null)) {
        return null;
    }

    return vehicleViews.value.reduce((sum, v) => sum + (v.summaryTax ?? 0), 0);
});
const totalDays = computed((): number =>
    vehicleViews.value.reduce((sum, v) => sum + v.summaryDays, 0),
);

// Vertical scroll sync between the three panes; the equality guard prevents loops.
const leftRef = ref<HTMLElement | null>(null);
const middleRef = ref<HTMLElement | null>(null);
const rightRef = ref<HTMLElement | null>(null);

// Measured vertical scrollbar width (varies by platform/DPR); re-measured via ResizeObserver.
const scrollbarWidth = ref(15);
const measureScrollbarWidth = (): void => {
    if (middleRef.value === null) {
return;
}

    const sw = middleRef.value.offsetWidth - middleRef.value.clientWidth;

    // -1 px buffer to absorb sub-pixel rounding (avoids clipping the last cell).
    if (sw > 0) {
        scrollbarWidth.value = sw - 1;
    }
};
let resizeObserver: ResizeObserver | null = null;
onMounted(() => {
    requestAnimationFrame(() => {
        measureScrollbarWidth();

        if (middleRef.value !== null && typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(measureScrollbarWidth);
            resizeObserver.observe(middleRef.value);
        }
    });
});
onUnmounted(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
});

// Sync scrollTop between the three panes; equality guard avoids the feedback loop.
function syncFrom(e: Event): void {
    const source = e.currentTarget as HTMLElement;
    const top = source.scrollTop;
    [leftRef.value, middleRef.value, rightRef.value].forEach((el) => {
        if (el !== null && el !== source && el.scrollTop !== top) {
            el.scrollTop = top;
        }
    });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-1">
            <HeatmapSummary
                :vehicles-count="vehicleViews.length"
                :total-days="totalDays"
                :total-annual-tax="totalAnnualTax"
                :fiscal-year="fiscalYear"
                :fiscal-supported="fiscalSupported"
            />
            <HeatmapLegend />
        </div>

        <p
            v-if="!fiscalSupported"
            class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-600"
        >
            Aucune règle fiscale n'est codée pour l'exercice {{ fiscalYear }} ·
            les taxes ne sont pas affichées. La saisie des locations et les loyers
            restent disponibles.
        </p>

        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <div class="flex">
                <div
                    ref="leftRef"
                    class="heatmap-pane shrink-0 max-h-[50em] overflow-y-auto bg-white scrollbar-hide-all"
                    @scroll="syncFrom"
                >
                    <div class="sticky top-0 z-10 bg-white pt-4 pb-2 pl-3 pr-2">
                        <div class="flex h-8 items-center">
                            <SortableHeader
                                label="Véhicule"
                                sort-key="licensePlate"
                                active-key="licensePlate"
                                :direction="sortDirection"
                                muted-label
                                @click="$emit('sort-toggle')"
                            />
                        </div>
                    </div>
                    <div
                        v-for="(view, idx) in vehicleViews"
                        :key="`left-${view.id}`"
                        class="pl-3 pr-2"
                        :class="idx > 0 && 'border-t border-slate-100'"
                    >
                        <VehicleInfo :vehicle-view="view" :fiscal-supported="fiscalSupported" />
                    </div>
                </div>

                <div class="min-w-0 flex-1 max-h-[50em] overflow-hidden bg-white">
                    <div
                        ref="middleRef"
                        class="heatmap-pane h-full overflow-auto"
                        :style="{ marginRight: `-${scrollbarWidth}px` }"
                        @scroll="syncFrom"
                    >
                        <div :style="{ width: `${gridContentWidth}px` }">
                        <div class="sticky top-0 z-10 bg-white pt-4 pb-2">
                            <div class="relative h-4 overflow-hidden">
                                <div
                                    v-for="label in monthLabels"
                                    :key="`m-${label.name}`"
                                    :style="{ left: label.leftCalc, width: label.widthCalc }"
                                    class="absolute top-0 truncate text-xs font-medium text-slate-500"
                                >
                                    {{ label.name }}
                                </div>
                            </div>
                            <div class="relative mt-1 h-3 overflow-hidden">
                                <div
                                    v-for="label in monthLabels"
                                    :key="`rent-${label.name}`"
                                    :style="{ left: label.leftCalc, width: label.widthCalc }"
                                    class="absolute top-0 truncate font-mono text-[10px] text-slate-500 tabular-nums"
                                >
                                    <template v-if="monthlyRentals === undefined">
                                        <span
                                            class="skeleton-shimmer inline-block h-2.5 w-10 rounded"
                                            aria-label="Calcul du loyer en cours"
                                        ></span>
                                    </template>
                                    <template v-else-if="monthlyRentals[label.monthIdx] === null">
                                        <span class="text-slate-300">-</span>
                                    </template>
                                    <template v-else>
                                        {{ formatEur(monthlyRentals[label.monthIdx]! / 100, 0) }}
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="relative">
                            <div
                                class="pointer-events-none absolute inset-y-0 left-0 right-0 grid gap-[1px]"
                                :style="{ gridTemplateColumns: gridColumns }"
                                aria-hidden="true"
                            >
                                <div
                                    v-for="(bg, weekIdx) in weekBackgrounds"
                                    :key="`bg-w${weekIdx}`"
                                    class="min-w-0"
                                    :style="{ background: bg }"
                                />
                            </div>
                            <div
                                v-for="(view, idx) in vehicleViews"
                                :key="`mid-${view.id}`"
                                class="relative"
                                :class="idx > 0 && 'border-t border-slate-100'"
                            >
                                <WeekCellsRow
                                    :vehicle-view="view"
                                    :fiscal-year="fiscalYear"
                                    @cell-click="$emit('cell-click', $event)"
                                />
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

                <div
                    ref="rightRef"
                    class="heatmap-pane shrink-0 w-28 max-h-[50em] overflow-y-auto bg-white"
                    @scroll="syncFrom"
                >
                    <div class="sticky top-0 z-10 bg-white pt-4 pb-2 pl-2 pr-3">
                        <div class="h-8" />
                    </div>
                    <div
                        v-for="(view, idx) in vehicleViews"
                        :key="`right-${view.id}`"
                        class="pl-2 pr-3"
                        :class="idx > 0 && 'border-t border-slate-100'"
                    >
                        <VehicleSummary :vehicle-view="view" :fiscal-supported="fiscalSupported" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Hide both scrollbars on the left pane. */
.scrollbar-hide-all {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.scrollbar-hide-all::-webkit-scrollbar {
    display: none;
}
</style>
