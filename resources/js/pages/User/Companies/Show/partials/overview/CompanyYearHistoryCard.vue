<script setup lang="ts">
/**
 * Tableau récapitulatif des **exercices passés** de l'entreprise — un
 * objet par année avec ≥ 1 contrat (chantier η Phase 1, doctrine
 * temporelle « Évolution »).
 *
 * **L'année calendaire courante n'apparaît pas ici** — elle vit dans
 * les KPIs en haut de page (`CompanyKpiCards`). Cette section est
 * exclusivement dédiée à l'évolution dans le temps : on regarde le
 * passé, pas le présent.
 *
 * Tri DESC (le plus récent en haut — convention dashboard rétrospectif).
 *
 * État vide : si l'entreprise n'a aucun contrat sur les exercices
 * passés (typiquement : entreprise créée cette année, ou jamais utilisée
 * historiquement), la carte affiche un message neutre plutôt que d'être
 * masquée — l'utilisateur sait que la section existe et pourquoi elle
 * est vide.
 */
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

type YearStats = App.Data.User.Company.CompanyYearStatsData;

const props = defineProps<{
    history: readonly YearStats[];
}>();

const sortedHistory = computed<YearStats[]>(() =>
    [...props.history].sort((a, b) => b.year - a.year),
);
</script>

<template>
    <Card>
        <template #header>
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
                        <td class="py-2 text-right tabular-nums text-slate-400">
                            —
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-2 text-[11px] text-slate-400">
                Format Contrats : total (LCD/LLD). Loyer : facturation V1.2.
            </p>
        </div>
    </Card>
</template>
