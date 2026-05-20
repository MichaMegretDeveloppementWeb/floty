<script setup lang="ts">
/**
 * Recap table of past fiscal years for the vehicle (current year is
 * shown in the KPIs above). Sorted DESC, with neutral rows for years
 * without contracts so the section never disappears.
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
                        <th class="py-2 pr-4 text-right">Locations</th>
                        <th class="py-2 pr-4 text-right">Taxe réelle</th>
                        <th class="py-2 pr-4 text-right">Taxe pleine</th>
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
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums text-slate-700">
                            {{ formatEur(entry.actualTax) }}
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums text-slate-500">
                            {{ formatEur(entry.fullYearTax) }}
                        </td>
                        <td class="py-2 text-right tabular-nums text-slate-500">
                            <template v-if="entry.rentalPrice !== null">
                                {{ formatEur(entry.rentalPrice) }}
                            </template>
                            <span v-else class="text-slate-300">·</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Card>
</template>
