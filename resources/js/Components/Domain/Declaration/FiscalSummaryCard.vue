<script setup lang="ts">
/**
 * Carte de synthèse fiscale d'une déclaration (Phase 11 D5.6, refondu
 * D5.8 avec breakdown par contrat agrégé temporairement par véhicule).
 *
 * Affiche les totaux fiscaux annuels (CO₂, polluants, total dû) et le
 * détail par véhicule. Consomme un `FiscalDeclarationSnapshotData`
 * calculé à la volée par le controller via `DeclarationFiscalEngine`
 * (D5.2). Les opt-outs Requalified (D5.1) sont déjà appliqués au
 * snapshot, donc les montants affichés correspondent à ce que le PDF
 * généré contiendra.
 *
 * **Shim D5.8.1** : le backend produit désormais `contractBreakdown`
 * (par contrat). Ce composant re-agrège par véhicule pour préserver
 * l'affichage actuel. Le composant `<DeclarationContractList>`
 * livré en D5.8.3 prendra le relais avec une vue chronologique par
 * contrat + clusters in-line, et ce shim sera retiré.
 */
import { Calculator } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    snapshot: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
}>();

type ContractEntry = App.Data.User.FiscalDeclaration.ContractSnapshotEntryData;
type VehicleAggregate = {
    vehicleId: number;
    vehicleLabel: string;
    daysAssigned: number;
    co2Due: number;
    pollutantsDue: number;
    totalDue: number;
};

/**
 * Re-agrège les rows par contrat (backend D5.8.1) en vue véhicule.
 * Shim temporaire : sera supprimé quand `<DeclarationContractList>`
 * remplacera ce composant en D5.8.3.
 */
const vehicleAggregates = computed<VehicleAggregate[]>(() => {
    const map = new Map<number, VehicleAggregate>();
    for (const entry of props.snapshot.contractBreakdown as ContractEntry[]) {
        const existing = map.get(entry.vehicleId);
        if (existing) {
            existing.daysAssigned += entry.daysInYearAssigned;
            existing.co2Due += entry.co2Due;
            existing.pollutantsDue += entry.pollutantsDue;
            existing.totalDue += entry.totalDue;
        } else {
            map.set(entry.vehicleId, {
                vehicleId: entry.vehicleId,
                vehicleLabel: entry.vehicleLabel,
                daysAssigned: entry.daysInYearAssigned,
                co2Due: entry.co2Due,
                pollutantsDue: entry.pollutantsDue,
                totalDue: entry.totalDue,
            });
        }
    }

    return Array.from(map.values());
});

const taxedVehicles = computed<VehicleAggregate[]>(() =>
    vehicleAggregates.value.filter((v) => v.totalDue > 0 && v.daysAssigned > 0),
);

const exemptedVehicles = computed<VehicleAggregate[]>(() =>
    vehicleAggregates.value.filter((v) => v.totalDue === 0 || v.daysAssigned === 0),
);

const hasVehicles = computed<boolean>(() => vehicleAggregates.value.length > 0);
const hasTaxed = computed<boolean>(() => taxedVehicles.value.length > 0);
const hasExempted = computed<boolean>(() => exemptedVehicles.value.length > 0);
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center gap-2">
                <Calculator :size="18" :stroke-width="1.75" class="text-slate-500" />
                <h2 class="text-base font-semibold text-slate-900">Synthèse fiscale</h2>
            </div>
        </template>

        <div class="flex flex-col gap-5">
            <dl class="flex flex-col gap-2 text-sm">
                <div class="flex items-baseline justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-600">Taxe CO₂ (CIBS L. 421-29)</dt>
                    <dd class="font-medium text-slate-900 tabular-nums">{{ formatEur(snapshot.co2DueTotal, 2) }}</dd>
                </div>
                <div class="flex items-baseline justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-600">Taxe polluants atmosphériques (CIBS L. 421-58)</dt>
                    <dd class="font-medium text-slate-900 tabular-nums">{{ formatEur(snapshot.pollutantsDueTotal, 2) }}</dd>
                </div>
                <div class="flex items-baseline justify-between pt-1">
                    <dt class="text-base font-semibold text-slate-900">Total dû</dt>
                    <dd class="text-lg font-semibold text-slate-900 tabular-nums">{{ formatEur(snapshot.totalDue, 2) }}</dd>
                </div>
            </dl>

            <div>
                <h3 class="mb-2 text-xs font-medium tracking-wider text-slate-500 uppercase">
                    Détail par véhicule
                </h3>
                <div v-if="!hasVehicles" class="rounded-lg bg-slate-50 px-4 py-3 text-sm italic text-slate-500">
                    Aucun véhicule attribué sur cet exercice.
                </div>
                <div v-else class="flex flex-col gap-3">
                    <div v-if="hasTaxed" class="overflow-hidden rounded-lg border border-slate-200">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-xs font-medium tracking-wide text-slate-500 uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Véhicule</th>
                                    <th class="px-3 py-2 text-right font-medium">Jours</th>
                                    <th class="px-3 py-2 text-right font-medium">CO₂</th>
                                    <th class="px-3 py-2 text-right font-medium">Polluants</th>
                                    <th class="px-3 py-2 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="row in taxedVehicles"
                                    :key="row.vehicleId"
                                    class="text-slate-700"
                                >
                                    <td class="px-3 py-2">{{ row.vehicleLabel }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ row.daysAssigned }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatEur(row.co2Due, 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatEur(row.pollutantsDue, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-slate-900 tabular-nums">{{ formatEur(row.totalDue, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <details v-if="hasExempted" class="overflow-hidden rounded-lg border border-emerald-200 bg-emerald-50/50">
                        <summary class="cursor-pointer px-3 py-2 text-xs font-medium tracking-wide text-emerald-800 uppercase">
                            {{ exemptedVehicles.length }} véhicule<template v-if="exemptedVehicles.length > 1">s</template> exonéré<template v-if="exemptedVehicles.length > 1">s</template> ou non taxé<template v-if="exemptedVehicles.length > 1">s</template> sur cet exercice
                        </summary>
                        <table class="w-full border-t border-emerald-200 bg-white text-sm">
                            <thead class="bg-emerald-50/50 text-xs font-medium tracking-wide text-emerald-700 uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Véhicule</th>
                                    <th class="px-3 py-2 text-right font-medium">Jours attribués</th>
                                    <th class="px-3 py-2 text-left font-medium">Motif</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-emerald-100">
                                <tr
                                    v-for="row in exemptedVehicles"
                                    :key="row.vehicleId"
                                    class="text-slate-700"
                                >
                                    <td class="px-3 py-2">{{ row.vehicleLabel }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ row.daysAssigned }}</td>
                                    <td class="px-3 py-2 text-xs italic text-emerald-700">
                                        <template v-if="row.daysAssigned === 0">Aucune attribution sur l'exercice</template>
                                        <template v-else>Totalement exonéré (motorisation propre, indispos, ou opt-out)</template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </details>
                </div>
            </div>
        </div>
    </Card>
</template>
