<script setup lang="ts">
/**
 * Recap table of past fiscal years for the company (current year is
 * shown in the KPIs above). Sorted DESC, with a neutral message when
 * the company has no past year on record.
 */
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

type YearStats = App.Data.User.Company.CompanyYearStatsData;

const props = withDefaults(
    defineProps<{
        history: readonly YearStats[];
        unwrapped?: boolean;
    }>(),
    { unwrapped: false },
);

const sortedHistory = computed<YearStats[]>(() =>
    [...props.history].sort((a, b) => b.year - a.year),
);
</script>

<template>
    <component :is="unwrapped ? 'div' : Card">
        <template v-if="!unwrapped" #header>
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                Historique par année
            </h2>
        </template>

        <div v-if="sortedHistory.length === 0" class="py-6 text-center text-sm italic text-slate-400">
            Aucun exercice passé pour cette entreprise.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Année</th>
                        <th class="py-2 pr-4 text-right">Jours</th>
                        <th class="py-2 pr-4 text-right">Locations</th>
                        <th class="py-2 pr-4 text-right">Taxes</th>
                        <th class="py-2 text-right">Montant loyer</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in sortedHistory"
                        :key="entry.year"
                        class="border-b border-slate-100 last:border-0"
                    >
                        <td class="py-2 pr-4 font-medium text-slate-900">
                            {{ entry.year }}
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums text-slate-700">
                            {{ entry.daysUsed }}
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums text-slate-700">
                            {{ entry.contractsCount }}
                            <span v-if="entry.lcdCount > 0 || entry.lldCount > 0" class="ml-1 text-xs text-slate-400">
                                ({{ entry.lcdCount }}/{{ entry.lldCount }})
                            </span>
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums text-slate-700">
                            {{ formatEur(entry.annualTaxDue) }}
                        </td>
                        <td class="py-2 text-right tabular-nums text-slate-700">
                            <span v-if="entry.rent !== null">{{ formatEur(entry.rent) }}</span>
                            <span v-else class="text-slate-400">·</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-2 text-[11px] text-slate-400">
                Format Locations : total (LCD/LLD). Montant loyer absent si au moins un véhicule a un tarif manquant sur l'année.
            </p>
        </div>
    </component>
</template>
