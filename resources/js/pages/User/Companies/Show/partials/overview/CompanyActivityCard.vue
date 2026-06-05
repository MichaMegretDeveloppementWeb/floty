<script setup lang="ts">
/**
 * Activity exploration card on the Company overview. Local year
 * selector (no Inertia reload, all years are pre-computed in the
 * `activityByYear` payload), with a monthly heatmap and a top-3
 * vehicles list.
 */
import { computed } from 'vue';
import { densityClass, textContrastClass } from '@/Components/Features/Planning/Heatmap/utils/density';
import Card from '@/Components/Ui/Card/Card.vue';
import YearSelector from '@/Components/Ui/YearSelector/YearSelector.vue';
import { useYearScope } from '@/Composables/Shared/useYearScope';
import { MONTH_LABELS } from '@/Utils/format/monthLabels';

type ActivityYear = App.Data.User.Company.CompanyActivityYearData;

const props = defineProps<{
    activityByYear: ActivityYear[];
    yearScope: App.Data.Shared.YearScopeData;
}>();

// Local mode: every year is pre-computed in `activityByYear`, so the
// year change only updates the URL (deep-link / F5 preserved).
const { selectedYear, selectedYearModel, availableYears } = useYearScope(
    props.yearScope,
);

// The picker exposes the global year scope. Years where this company
// has no contract render the empty state below.
const sortedYears = computed<readonly number[]>(() =>
    [...availableYears.value].sort((a, b) => b - a),
);

// Lookup local dans le pré-calcul backend. Si l'année sélectionnée n'a
// pas d'entrée (cas : year ∈ scope global mais pas dans availableYears
// de cette company), on retourne une activité vide neutre.
function emptyActivity(year: number): ActivityYear {
    return {
        year,
        daysByMonth: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        topVehicles: [],
    };
}

const byYear = computed<ActivityYear>(
    () =>
        props.activityByYear.find((entry) => entry.year === selectedYear.value)
        ?? emptyActivity(selectedYear.value),
);

// Density palette is normalised to 0..7 so it can reuse the shared
// `densityClass` scale (0 = white, 7 = dark blue).
// Single-letter month labels are intentionally not shared with
// `MONTH_LABELS` (see its doc-block).
const monthLabels = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'] as const;
const fullMonthNames = MONTH_LABELS.map((m) => m.toLowerCase()) as readonly string[];

const maxMonth = computed<number>(() => Math.max(0, ...byYear.value.daysByMonth));

function densityForMonth(daysInMonth: number): number {
    if (maxMonth.value === 0) {
        return 0;
    }

    return Math.round((daysInMonth / maxMonth.value) * 7);
}

const isEmpty = computed<boolean>(
    () => byYear.value.topVehicles.length === 0 && maxMonth.value === 0,
);

function formatPercentage(value: number): string {
    return value.toLocaleString('fr-FR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    });
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                    Activité
                </h2>
                <YearSelector
                    v-model="selectedYearModel"
                    :available-years="sortedYears"
                />
            </div>
        </template>

        <div v-if="isEmpty" class="py-6 text-center text-sm italic text-slate-400">
            Aucune activité enregistrée pour cet exercice.
        </div>

        <div v-else class="flex flex-col gap-6">
            <section>
                <p class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">
                    Occupation mensuelle
                </p>
                <div class="grid grid-cols-12 gap-1">
                    <div
                        v-for="(days, idx) in byYear.daysByMonth"
                        :key="idx"
                        class="flex flex-col items-center gap-1"
                    >
                        <span
                            :class="[
                                densityClass(densityForMonth(days)),
                                textContrastClass(densityForMonth(days)),
                                'flex h-8 w-full items-center justify-center rounded-[3px] font-mono text-xs',
                            ]"
                            :title="`${fullMonthNames[idx]} : ${days} jour${days > 1 ? 's' : ''}-véhicule${days > 1 ? 's' : ''}`"
                        >
                            {{ days > 0 ? days : '' }}
                        </span>
                        <span class="text-[10px] font-medium text-slate-400">
                            {{ monthLabels[idx] }}
                        </span>
                    </div>
                </div>
            </section>

            <section>
                <p class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">
                    Top véhicules
                </p>
                <ol class="flex flex-col gap-2">
                    <li
                        v-for="(vehicle, idx) in byYear.topVehicles"
                        :key="vehicle.vehicleId"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span class="w-4 shrink-0 text-right text-xs font-medium text-slate-400">
                            {{ idx + 1 }}.
                        </span>
                        <span class="w-32 shrink-0 font-mono text-slate-900">
                            {{ vehicle.licensePlate }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-slate-600">
                            {{ vehicle.brand }} {{ vehicle.model }}
                        </span>
                        <span class="w-16 shrink-0 text-right tabular-nums text-slate-600">
                            {{ vehicle.daysUsed }} j
                        </span>
                        <span class="hidden h-1.5 w-24 shrink-0 overflow-hidden rounded bg-slate-100 sm:block">
                            <span
                                class="block h-full rounded bg-blue-500"
                                :style="{ width: `${vehicle.percentage}%` }"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="w-12 shrink-0 text-right tabular-nums text-xs text-slate-500">
                            {{ formatPercentage(vehicle.percentage) }}&nbsp;%
                        </span>
                    </li>
                </ol>
            </section>
        </div>
    </Card>
</template>
