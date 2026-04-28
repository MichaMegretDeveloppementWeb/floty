<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import { companyColorBgClass } from '@/Utils/colors/companyColor';

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

type Segment = App.Data.User.Vehicle.VehicleWeekSegmentData;

// Convention design system : 12 mois → 4-4-5-4-4-5-4-4-5-4-4-5 = 52
// (cohérent avec le composant Heatmap planning).
const monthLabels = [
    { name: 'Jan', weeks: 4 },
    { name: 'Fév', weeks: 4 },
    { name: 'Mar', weeks: 5 },
    { name: 'Avr', weeks: 4 },
    { name: 'Mai', weeks: 4 },
    { name: 'Juin', weeks: 5 },
    { name: 'Juil', weeks: 4 },
    { name: 'Août', weeks: 4 },
    { name: 'Sept', weeks: 5 },
    { name: 'Oct', weeks: 4 },
    { name: 'Nov', weeks: 4 },
    { name: 'Déc', weeks: 5 },
];

const totalVehicleDays = computed<number>(() =>
    props.stats.weeklyBreakdown.reduce((sum, w) => sum + w.totalDays, 0),
);

const heightForDays = (days: number): string => `${(days / 7) * 100}%`;
const heightFor = (segment: Segment): string => heightForDays(segment.days);

// Légende = liste des entreprises ayant utilisé le véhicule sur l'année,
// triée par jours décroissants (réutilise le tri du breakdown global).
const legendEntries = computed<App.Data.User.Vehicle.VehicleCompanyUsageData[]>(
    () => props.stats.companies,
);
</script>

<template>
    <Card>
        <template #header>
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Utilisation annuelle
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Répartition par entreprise · {{ totalVehicleDays }}
                    jour{{ totalVehicleDays > 1 ? 's' : '' }}-véhicule
                </p>
            </div>
        </template>

        <div class="flex flex-col gap-3">
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
                            v-for="week in props.stats.weeklyBreakdown"
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
                                <!-- Segment indispo empilé au-dessus des
                                     attributions, hauteur proportionnelle
                                     aux jours réels (sur 7). Croix en
                                     lignes 1px pour rester subtile. -->
                                <div
                                    v-if="week.unavailabilityDays > 0"
                                    :style="{ height: heightForDays(week.unavailabilityDays) }"
                                    class="relative w-full bg-rose-50/60"
                                >
                                    <svg
                                        class="pointer-events-none absolute inset-0 h-full w-full text-rose-400"
                                        preserveAspectRatio="none"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1"
                                        vector-effect="non-scaling-stroke"
                                        aria-hidden="true"
                                    >
                                        <line x1="2" y1="2" x2="14" y2="14" />
                                        <line x1="14" y1="2" x2="2" y2="14" />
                                    </svg>
                                </div>
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
                                        <span class="font-medium">
                                            {{ segment.shortCode }}
                                        </span>
                                        <span class="text-slate-300">
                                            {{ segment.days }}j
                                        </span>
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
                                        <span class="text-slate-300">
                                            {{ week.unavailabilityDays }}j
                                        </span>
                                    </li>
                                </ul>
                            </template>
                        </Tooltip>
                    </div>
                </div>
            </div>

            <!-- Légende : pastilles couleur + nom + jours, tri par jours desc -->
            <ul
                v-if="legendEntries.length > 0"
                class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3"
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
        </div>
    </Card>
</template>
