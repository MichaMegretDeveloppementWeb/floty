<script setup lang="ts">
/**
 * Tableau récapitulatif exercice par exercice — comparaison directe
 * sans bascule de sélecteur (chantier K, ADR-0020 D3 — section
 * Historique).
 *
 * Le marqueur ● sur la ligne de l'année calendaire en cours signale
 * « exercice non clos / en cours » et différencie d'un exercice
 * historique terminé.
 */
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

type YearStats = App.Data.User.Company.CompanyYearStatsData;

const props = defineProps<{
    history: readonly YearStats[];
    currentRealYear: number;
}>();

// Tri DESC (le plus récent en haut — convention dashboard rétrospectif)
const sortedHistory = computed<YearStats[]>(() =>
    [...props.history].sort((a, b) => b.year - a.year),
);
</script>

<template>
    <Card v-if="sortedHistory.length > 0">
        <template #header>
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                Historique par année
            </h2>
        </template>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Année</th>
                        <th class="py-2 pr-4 text-right">Jours</th>
                        <th class="py-2 pr-4 text-right">Contrats</th>
                        <th class="py-2 pr-4 text-right">Taxes</th>
                        <th class="py-2 text-right">Loyer</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in sortedHistory"
                        :key="entry.year"
                        class="border-b border-slate-100 last:border-0"
                    >
                        <td class="py-2 pr-4 font-medium text-slate-900">
                            <span class="inline-flex items-center gap-1.5">
                                {{ entry.year }}
                                <span
                                    v-if="entry.year === currentRealYear"
                                    class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700"
                                    title="Exercice en cours"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
                                    en cours
                                </span>
                            </span>
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
                        <td class="py-2 text-right tabular-nums text-slate-400">
                            —
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-[11px] text-slate-400">
            Format Contrats : total (LCD/LLD). Loyer : facturation V1.2.
        </p>
    </Card>
</template>
