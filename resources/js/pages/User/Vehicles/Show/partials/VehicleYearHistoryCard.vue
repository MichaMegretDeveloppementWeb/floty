<script setup lang="ts">
/**
 * Tableau récapitulatif des **exercices passés** du véhicule — un objet
 * par année dans `[minYear..currentYear-1]` du scope global, lignes
 * neutres comprises pour les années sans contrat sur ce véhicule.
 *
 * Doctrine temporelle (chantier η Phase 2, lentille « Évolution ») :
 * l'année calendaire courante n'apparaît pas ici (déjà dans les KPIs en
 * haut). On regarde le passé, pas le présent.
 *
 * Tri DESC (le plus récent en haut — convention dashboard rétrospectif).
 *
 * État vide (pas d'exercice passé, typiquement véhicule créé cette
 * année) : message neutre plutôt que masquage de la carte — l'utilisateur
 * sait que la section existe et pourquoi elle est vide.
 */
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

type YearStats = App.Data.User.Vehicle.VehicleYearStatsData;

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
            Aucun exercice passé pour ce véhicule.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Année</th>
                        <th class="py-2 pr-4 text-right">Jours</th>
                        <th class="py-2 pr-4 text-right">Contrats</th>
                        <th class="py-2 pr-4 text-right">Taxe réelle</th>
                        <th class="py-2 text-right">Coût plein</th>
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
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums text-slate-700">
                            {{ formatEur(entry.actualTax) }}
                        </td>
                        <td class="py-2 text-right tabular-nums text-slate-500">
                            {{ formatEur(entry.fullYearTax) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Card>
</template>
