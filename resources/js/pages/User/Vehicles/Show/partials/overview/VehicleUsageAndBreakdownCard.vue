<script setup lang="ts">
/**
 * Carte unifiée **Utilisation annuelle + Répartition fiscale par
 * entreprise** pour la fiche véhicule (chantier η Phase 2 — onglet
 * Vue d'ensemble).
 *
 * Avant : 2 cards distinctes (`VehicleYearlyUsageTimeline` +
 * `CompanyFiscalBreakdownTable`) chacune sur son année. Désormais : 1
 * seule card avec sélecteur d'année intégré dans le header, Timeline
 * en haut, tableau Répartition en bas. Les 2 sont pilotées par la même
 * année.
 *
 * **Lazy loading + cache** (composable `useYearLazy`) : l'année initiale
 * (= currentYear) vient du payload Inertia. Quand l'utilisateur change
 * l'année, on fetch JSON ciblé via `usageStats.url(...)` (Wayfinder),
 * on cache, on affiche. Une année déjà visitée → affichage instantané.
 */
import { computed, watch } from 'vue';
import { usageStats as usageStatsRoute } from '@/actions/App/Http/Controllers/User/Vehicle/VehicleController';
import Card from '@/Components/Ui/Card/Card.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import YearSelector from '@/Components/Ui/YearSelector/YearSelector.vue';
import { useYearLazy } from '@/Composables/Shared/useYearLazy';
import { useCompanyFiscalBreakdownTable } from '@/Composables/Vehicle/Show/useCompanyFiscalBreakdownTable';
import { useVehicleYearlyUsageTimeline } from '@/Composables/Vehicle/Show/useVehicleYearlyUsageTimeline';
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

// Auto-update timeline + breakdown après CRUD indispo : le parent
// `VehicleOverviewTab` reçoit un nouveau `vehicle.usageStats` après
// `router.delete/post/patch` (Inertia full reload sans `only:`).
// Le watch détecte la nouvelle référence d'`initialStats` et invalide
// le cache. Si l'utilisateur est sur l'année initiale, propagation
// instantanée ; sinon refetch de l'année actuelle.
watch(
    () => props.initialStats,
    (fresh, previous) => {
        if (fresh === previous) {
            return;
        }

        void invalidate(fresh);
    },
);

// Wrapper proxy : les composables accèdent à `props.stats.xxx` dans
// leurs computed internes. Avec un getter, chaque accès relit la valeur
// courante de `data` → réactivité préservée à travers les fetch lazy.
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
/**
 * Indisponibilité : pattern de hachures diagonales rose +
 * background plein. Plus lisible que la croix SVG précédente
 * (visible même sur des bandes très étroites).
 */
.unavailability-segment {
    background-color: rgb(254 205 211); /* rose-200 */
    background-image: repeating-linear-gradient(
        135deg,
        rgb(225 29 72 / 0.55) 0,
        rgb(225 29 72 / 0.55) 1.5px,
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
                        Timeline 52 semaines + ventilation fiscale par entreprise —
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
            <!-- ============== Timeline 52 semaines ============== -->
            <section>
                <div class="overflow-x-auto">
                    <div class="inline-flex min-w-full flex-col">
                        <!-- Labels mensuels alignés sur les groupes de semaines -->
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

                        <!-- Timeline 52 cellules avec tooltip custom -->
                        <div class="flex h-10">
                            <Tooltip
                                v-for="week in stats.weeklyBreakdown"
                                :key="week.weekNumber"
                            >
                                <div
                                    :class="[
                                        'relative flex h-10 w-[16px] flex-col-reverse overflow-hidden border-r border-white last:border-r-0',
                                        week.totalDays === 0 && week.unavailabilityDays === 0 ? 'bg-slate-100' : '',
                                    ]"
                                >
                                    <div
                                        v-for="segment in week.segments"
                                        :key="segment.companyId"
                                        :class="companyColorBgClass(segment.color)"
                                        :style="{ height: heightFor(segment) }"
                                    />
                                    <div
                                        v-if="week.unavailabilityDays > 0"
                                        :style="{ height: heightForDays(week.unavailabilityDays) }"
                                        class="unavailability-segment relative w-full"
                                    />
                                </div>

                                <template #content>
                                    <p class="font-semibold text-slate-200">
                                        Semaine {{ week.weekNumber }}
                                    </p>
                                    <p
                                        v-if="week.segments.length === 0 && week.unavailabilityDays === 0"
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
                                            v-if="week.unavailabilityDays > 0"
                                            class="flex items-center gap-2"
                                        >
                                            <span
                                                class="inline-block h-2 w-2 shrink-0 rounded-sm bg-rose-300"
                                                aria-hidden="true"
                                            />
                                            <span class="font-medium">Indispo</span>
                                            <span class="text-slate-300">{{ week.unavailabilityDays }}j</span>
                                        </li>
                                    </ul>
                                </template>
                            </Tooltip>
                        </div>
                    </div>
                </div>

                <!-- Légende -->
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

            <!-- ============== Tableau Répartition fiscale ============== -->
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
                        <span class="text-slate-500">{{ Number(value).toFixed(1) }}%</span>
                    </template>
                    <template #cell-taxCo2="{ value }">
                        {{ formatEur(Number(value)) }}
                    </template>
                    <template #cell-taxPollutants="{ value }">
                        {{ formatEur(Number(value)) }}
                    </template>
                    <template #cell-taxTotal="{ value }">
                        <span class="font-semibold text-slate-900">{{ formatEur(Number(value)) }}</span>
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
