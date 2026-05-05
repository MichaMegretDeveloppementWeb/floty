<script setup lang="ts">
/**
 * Section « Activité » de la fiche entreprise — lentille **Exploration**
 * de la doctrine temporelle (chantier η Phase 1, 2026-05-05).
 *
 * Une carte unique, deux visualisations empilées pilotées par un
 * sélecteur d'année **local** (mode local — pas de reload Inertia, les
 * données de toutes années sont déjà pré-calculées dans
 * `company.activityByYear`) :
 *
 *   1. Heatmap mensuelle : 12 cases (J → D), saturation calée sur le
 *      mois le plus chargé de l'année (échelle 0..7 réutilisant la
 *      `densityClass` du design system Heatmap planning).
 *   2. Top 3 véhicules : licence plate + marque/modèle + jours +
 *      pourcentage du total annuel jours-véhicules de l'entreprise.
 *
 * **Migration chantier η** : remplace l'ancien `useCompanySelectedYear`
 * par `useYearScope` (générique, partagé avec toutes les pages). Le
 * composant `YearSelector` remplace le `<select>` natif inline. Les
 * bornes du sélecteur viennent désormais de `company.yearScope`
 * (calculées globalement par `AvailableYearsResolver`).
 *
 * État vide (année hors `availableYears` propres à la company, ou aucune
 * activité) : heatmap blanche + message « Aucune activité enregistrée
 * pour cet exercice ».
 */
import { computed } from 'vue';
import { densityClass, textContrastClass } from '@/Components/Features/Planning/Heatmap/utils/density';
import Card from '@/Components/Ui/Card/Card.vue';
import YearSelector from '@/Components/Ui/YearSelector/YearSelector.vue';
import { useYearScope } from '@/Composables/Shared/useYearScope';

type Company = App.Data.User.Company.CompanyDetailData;
type ActivityYear = App.Data.User.Company.CompanyActivityYearData;

const props = defineProps<{
    company: Company;
}>();

// Mode local : pas de `reloadKeys` — toutes les années sont déjà
// pré-calculées côté backend dans `activityByYear`. Le changement
// d'année met juste à jour l'URL (deep-link / refresh F5 préservé).
//
// `selectedYearModel` est utilisé pour le `v-model` du YearSelector
// (passe par `selectYear()` qui sync l'URL). `selectedYear` est
// utilisé en lecture seule pour le lookup `byYear`.
const { selectedYear, selectedYearModel, availableYears } = useYearScope(
    props.company.yearScope,
);

// Le scope front (`company.yearScope.availableYears`) est global —
// toutes les années où le système a au moins un contrat. C'est ce que
// le sélecteur expose. Si la company spécifique n'a pas de contrat sur
// une année donnée, on rendra l'état vide ci-dessous.
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
        props.company.activityByYear.find((entry) => entry.year === selectedYear.value)
        ?? emptyActivity(selectedYear.value),
);

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
