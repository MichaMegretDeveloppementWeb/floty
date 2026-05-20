<script setup lang="ts">
/**
 * Single card that combines the 52-week usage timeline and the per-company
 * fiscal breakdown table. Both panels share a single year selector and
 * lazy-load yearly data via `useYearLazy` + a JSON fetch (Wayfinder URL).
 */
import { computed, watch } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import YearSelector from '@/Components/Ui/YearSelector/YearSelector.vue';
import { useYearLazy } from '@/Composables/Shared/useYearLazy';
import { useCompanyFiscalBreakdownTable } from '@/Composables/Vehicle/Show/useCompanyFiscalBreakdownTable';
import { useVehicleYearlyUsageTimeline } from '@/Composables/Vehicle/Show/useVehicleYearlyUsageTimeline';
import { usageStats as usageStatsRoute } from '@/routes/user/vehicles';
import { companyColorBgClass } from '@/Utils/colors/companyColor';
import { formatEur } from '@/Utils/format/formatEur';

type UsageStats = App.Data.User.Vehicle.VehicleUsageStatsData;

const props = defineProps<{
    vehicleId: number;
    initialStats: UsageStats;
    availableYears: readonly number[];
}>();

const { yearModel, data, isLoading, invalidate } = useYearLazy<UsageStats>(
    props.initialStats.fiscalYear,
    props.initialStats,
    async (year) => {
        const url = usageStatsRoute.url(props.vehicleId, { query: { year } });
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return (await response.json()) as UsageStats;
    },
);

// After an unavailability CRUD, the parent receives a fresh
// `initialStats` via a full Inertia reload. This watcher invalidates
// the year cache so the timeline and breakdown reflect the new data.
watch(
    () => props.initialStats,
    (fresh, previous) => {
        if (fresh === previous) {
            return;
        }

        void invalidate(fresh);
    },
);

// Proxy with a getter so the sibling composables see the latest stats
// returned by the lazy loader on every access.
const stats = computed<UsageStats>(() => data.value ?? props.initialStats);
const composableArg = {
    get stats() {
        return stats.value;
    },
};

const {
    monthLabels,
    totalVehicleDays,
    legendEntries,
    heightForDays,
    heightFor,
} = useVehicleYearlyUsageTimeline(composableArg);

const {
    columns,
    totalDays,
    totalProrato,
    totalCo2,
    totalPollutants,
    totalAll,
    initialsOf,
} = useCompanyFiscalBreakdownTable(composableArg);
</script>

<style scoped>
/* Reductive unavailability (R-2024-008): rose hatch, fiscal alert.
   Non-reductive: slate hatch, neutral operational info.
   Both overlays are absolute so they can coexist with the contract bar (ADR-0019). */
.unavailability-segment-reductive {
    background-color: rgb(254 205 211 / 0.7); /* rose-200 transparent */
    background-image: repeating-linear-gradient(
        135deg,
        rgb(225 29 72 / 0.55) 0,
        rgb(225 29 72 / 0.55) 1.5px,
        transparent 1.5px,
        transparent 4px
    );
}

.unavailability-segment-non-reductive {
    background-color: rgb(226 232 240 / 0.7); /* slate-200 transparent */
    background-image: repeating-linear-gradient(
        135deg,
        rgb(100 116 139 / 0.55) 0,
        rgb(100 116 139 / 0.55) 1.5px,
        transparent 1.5px,
        transparent 4px
    );
}
</style>

<template>
    <Card>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Utilisation annuelle &amp; répartition
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Timeline 52 semaines + ventilation fiscale par entreprise ·
                        {{ totalVehicleDays }}
                        jour{{ totalVehicleDays > 1 ? 's' : '' }}-véhicule
                    </p>
                </div>
                <YearSelector
                    v-model="yearModel"
                    :available-years="availableYears"
                    :disabled="isLoading"
                />
            </div>
        </template>

        <div class="flex flex-col gap-6" :class="{ 'opacity-60': isLoading }">
            <section>
                <div class="overflow-x-auto">
                    <div class="inline-flex min-w-full flex-col">
                        <div class="mb-2 flex h-4">
                            <div
                                v-for="month in monthLabels"
                                :key="month.name"
                                :style="{ width: `${month.weeks * 16}px` }"
                                class="text-xs font-medium text-slate-500"
                            >
                                {{ month.name }}
                            </div>
                        </div>

                        <div class="flex h-10">
                            <Tooltip
                                v-for="week in stats.weeklyBreakdown"
                                :key="week.weekNumber"
                            >
                                <div
                                    :class="[
                                        'relative flex h-10 w-[16px] flex-col-reverse overflow-hidden border-r border-white last:border-r-0',
                                        week.totalDays === 0
                                            && week.reductiveUnavailabilityDays === 0
                                            && week.nonReductiveUnavailabilityDays === 0
                                            ? 'bg-slate-100'
                                            : '',
                                    ]"
                                >
                                    <div
                                        v-for="segment in week.segments"
                                        :key="segment.companyId"
                                        :class="companyColorBgClass(segment.color)"
                                        :style="{ height: heightFor(segment) }"
                                    />
                                    <div
                                        v-if="week.reductiveUnavailabilityDays > 0"
                                        class="unavailability-segment-reductive pointer-events-none absolute inset-x-0 top-0"
                                        :style="{ height: heightForDays(week.reductiveUnavailabilityDays) }"
                                    />
                                    <div
                                        v-if="week.nonReductiveUnavailabilityDays > 0"
                                        class="unavailability-segment-non-reductive pointer-events-none absolute inset-x-0"
                                        :style="{
                                            top: heightForDays(week.reductiveUnavailabilityDays),
                                            height: heightForDays(week.nonReductiveUnavailabilityDays),
                                        }"
                                    />
                                </div>

                                <template #content>
                                    <p class="font-semibold text-slate-200">
                                        Semaine {{ week.weekNumber }}
                                    </p>
                                    <p
                                        v-if="week.segments.length === 0
                                            && week.reductiveUnavailabilityDays === 0
                                            && week.nonReductiveUnavailabilityDays === 0"
                                        class="text-slate-300"
                                    >
                                        Pas d'utilisation
                                    </p>
                                    <ul v-else class="mt-1 flex flex-col gap-1">
                                        <li
                                            v-for="segment in week.segments"
                                            :key="segment.companyId"
                                            class="flex items-center gap-2"
                                        >
                                            <span
                                                :class="[
                                                    'inline-block h-2 w-2 shrink-0 rounded-sm',
                                                    companyColorBgClass(segment.color),
                                                ]"
                                                aria-hidden="true"
                                            />
                                            <span class="font-medium">{{ segment.shortCode }}</span>
                                            <span class="text-slate-300">{{ segment.days }}j</span>
                                        </li>
                                        <li
                                            v-if="week.reductiveUnavailabilityDays > 0"
                                            class="flex items-center gap-2"
                                        >
                                            <span
                                                class="inline-block h-2 w-2 shrink-0 rounded-sm bg-rose-300"
                                                aria-hidden="true"
                                            />
                                            <span class="font-medium">Indispo réductrice</span>
                                            <span class="text-slate-300">{{ week.reductiveUnavailabilityDays }}j</span>
                                        </li>
                                        <li
                                            v-if="week.nonReductiveUnavailabilityDays > 0"
                                            class="flex items-center gap-2"
                                        >
                                            <span
                                                class="inline-block h-2 w-2 shrink-0 rounded-sm bg-slate-300"
                                                aria-hidden="true"
                                            />
                                            <span class="font-medium">Indispo opérationnelle</span>
                                            <span class="text-slate-300">{{ week.nonReductiveUnavailabilityDays }}j</span>
                                        </li>
                                    </ul>
                                </template>
                            </Tooltip>
                        </div>
                    </div>
                </div>

                <ul
                    v-if="legendEntries.length > 0"
                    class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3"
                >
                    <li
                        v-for="entry in legendEntries"
                        :key="entry.companyId"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span
                            :class="[
                                'inline-block h-2.5 w-2.5 shrink-0 rounded-sm',
                                companyColorBgClass(entry.color),
                            ]"
                            aria-hidden="true"
                        />
                        <span class="text-slate-700">{{ entry.legalName }}</span>
                        <span class="font-mono text-xs text-slate-500">
                            {{ entry.daysUsed }}j
                        </span>
                    </li>
                </ul>
            </section>

            <section>
                <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                    Répartition fiscale par entreprise utilisatrice
                </h3>
                <p
                    v-if="stats.companies.length === 0"
                    class="text-sm italic text-slate-500"
                >
                    Aucune entreprise utilisatrice cette année.
                </p>

                <DataTable
                    v-else
                    :columns="columns"
                    :rows="stats.companies"
                    :row-key="(row) => row.companyId"
                >
                    <template #cell-shortCode="{ row }">
                        <CompanyTag
                            :name="row.legalName"
                            :initials="initialsOf(row.shortCode)"
                            :color="row.color"
                        />
                    </template>
                    <template #cell-proratoPercent="{ value }">
                        <span class="whitespace-nowrap text-slate-500">{{ Number(value).toFixed(1) }}%</span>
                    </template>
                    <template #cell-taxCo2="{ value }">
                        <span class="whitespace-nowrap">{{ formatEur(Number(value)) }}</span>
                    </template>
                    <template #cell-taxPollutants="{ value }">
                        <span class="whitespace-nowrap">{{ formatEur(Number(value)) }}</span>
                    </template>
                    <template #cell-taxTotal="{ value }">
                        <span class="font-semibold whitespace-nowrap text-slate-900">{{ formatEur(Number(value)) }}</span>
                    </template>

                    <template #footer-row>
                        <td class="px-[18px] py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Total {{ stats.fiscalYear }}
                        </td>
                        <td class="px-[18px] py-2.5 text-right font-mono text-sm font-semibold text-slate-900 tabular-nums">
                            {{ totalDays }}
                        </td>
                        <td class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-500 tabular-nums">
                            {{ totalProrato.toFixed(1) }}%
                        </td>
                        <td class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-700 tabular-nums">
                            {{ formatEur(totalCo2) }}
                        </td>
                        <td class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-700 tabular-nums">
                            {{ formatEur(totalPollutants) }}
                        </td>
                        <td class="px-[18px] py-2.5 text-right font-mono text-sm font-semibold text-slate-900 tabular-nums">
                            {{ formatEur(totalAll) }}
                        </td>
                    </template>
                </DataTable>
            </section>
        </div>
    </Card>
</template>
