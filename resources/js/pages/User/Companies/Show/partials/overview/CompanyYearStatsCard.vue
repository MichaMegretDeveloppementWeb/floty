<script setup lang="ts">
/**
 * Section « Aperçu par année » : 4 KPIs annuels (jours, contrats,
 * taxes, loyer placeholder V1.2) pilotés par un sélecteur d'année
 * **local**, sync URL via `useCompanySelectedYear` (chantier K,
 * ADR-0020 D3).
 */
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

type YearStats = App.Data.User.Company.CompanyYearStatsData;

const props = defineProps<{
    byYear: YearStats;
    availableYears: readonly number[];
    currentRealYear: number;
    selectedYear: number;
}>();

const emit = defineEmits<{
    'update:selectedYear': [year: number];
}>();

// Options du sélecteur : `availableYears` triés desc + `currentRealYear`
// si pas déjà dedans (l'utilisateur peut toujours revenir au présent
// même si l'entreprise n'a aucun contrat sur l'année réelle).
const yearOptions = computed<number[]>(() => {
    const set = new Set<number>(props.availableYears);
    set.add(props.currentRealYear);

    return Array.from(set).sort((a, b) => b - a);
});

const selectedModel = computed<number>({
    get: () => props.selectedYear,
    set: (value: number) => emit('update:selectedYear', value),
});
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                    Aperçu par année
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

        <div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
            <div class="flex flex-col gap-1">
                <p class="text-2xl font-semibold text-slate-900 tabular-nums">
                    {{ byYear.daysUsed }}
                </p>
                <p class="text-xs text-slate-500">
                    jour{{ byYear.daysUsed > 1 ? 's' : '' }} d'usage
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p class="text-2xl font-semibold text-slate-900 tabular-nums">
                    {{ byYear.contractsCount }}
                </p>
                <p class="text-xs text-slate-500">
                    <span v-if="byYear.contractsCount === 0">aucun contrat</span>
                    <span v-else>
                        contrat{{ byYear.contractsCount > 1 ? 's' : '' }}
                        <span v-if="byYear.lcdCount > 0 || byYear.lldCount > 0" class="text-slate-400">
                            · {{ byYear.lcdCount }} LCD · {{ byYear.lldCount }} LLD
                        </span>
                    </span>
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p class="text-2xl font-semibold text-slate-900 tabular-nums">
                    {{ formatEur(byYear.annualTaxDue) }}
                </p>
                <p class="text-xs text-slate-500">
                    taxe annuelle
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p class="text-2xl font-semibold text-slate-400 tabular-nums">
                    —
                </p>
                <p class="text-xs text-slate-500">
                    loyer
                    <span class="ml-1 inline-block rounded bg-slate-100 px-1 py-px text-[10px] font-medium uppercase tracking-wide text-slate-500">
                        V1.2
                    </span>
                </p>
            </div>
        </div>
    </Card>
</template>
