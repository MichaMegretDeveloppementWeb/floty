<script setup lang="ts">
/**
 * Section « Activité » de la fiche entreprise (chantier K L2, ADR-0020 D3).
 *
 * Une carte unique, deux visualisations empilées pilotées par un
 * sélecteur d'année **local** (sync URL `?year=YYYY`) :
 *
 *   1. Heatmap mensuelle : 12 cases (J → D), saturation calée sur le
 *      mois le plus chargé de l'année (échelle 0..7 réutilisant la
 *      `densityClass` du design system Heatmap planning).
 *   2. Top 3 véhicules : licence plate + marque/modèle + jours +
 *      pourcentage du total annuel jours-véhicules de l'entreprise.
 *
 * État vide (année hors `availableYears`, ou aucune activité) :
 * heatmap blanche + message « Aucun véhicule attribué sur cet exercice ».
 */
import { computed, toRef } from 'vue';
import { densityClass, textContrastClass } from '@/Components/Features/Planning/Heatmap/utils/density';
import Card from '@/Components/Ui/Card/Card.vue';
import { useCompanySelectedYear } from '@/Composables/Company/Show/useCompanySelectedYear';

type Company = App.Data.User.Company.CompanyDetailData;

const props = defineProps<{
    company: Company;
}>();

const { selectedYear, byYear, setSelectedYear } = useCompanySelectedYear({
    activityByYear: toRef(() => props.company.activityByYear),
    availableYears: toRef(() => props.company.availableYears),
    currentRealYear: toRef(() => props.company.currentRealYear),
});

// Options du sélecteur : `availableYears` (desc) + `currentRealYear`
// si pas déjà dedans (l'utilisateur peut toujours revenir au présent
// même si l'entreprise n'a aucun contrat sur l'année réelle).
const yearOptions = computed<number[]>(() => {
    const set = new Set<number>(props.company.availableYears);
    set.add(props.company.currentRealYear);

    return Array.from(set).sort((a, b) => b - a);
});

const selectedModel = computed<number>({
    get: () => selectedYear.value,
    set: (value: number) => setSelectedYear(value),
});

// Échelle de densité : on normalise à 0..7 par division par le max
// du mois le plus chargé de l'année. Permet de réutiliser la palette
// `densityClass` (0 = blanc bordé, 7 = bleu foncé) du design system.
const monthLabels = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'] as const;
const fullMonthNames = [
    'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
] as const;

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
    // Locale FR — virgule décimale, 1 chiffre après
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
                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <span class="font-medium">Année</span>
                    <select
                        v-model.number="selectedModel"
                        class="rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-900 transition-colors duration-[120ms] ease-out hover:bg-slate-50 focus:outline-none focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)]"
                    >
                        <option
                            v-for="year in yearOptions"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>
                </label>
            </div>
        </template>

        <div v-if="isEmpty" class="py-6 text-center text-sm italic text-slate-400">
            Aucune activité enregistrée pour cet exercice.
        </div>

        <div v-else class="flex flex-col gap-6">
            <!-- Heatmap mensuelle -->
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

            <!-- Top véhicules -->
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
