<script setup lang="ts">
/**
 * Carte de synthèse fiscale d'une déclaration (Phase 11 D5.6).
 *
 * Affiche les totaux fiscaux annuels (CO₂, polluants, total dû) et le
 * détail par véhicule. Consomme un `FiscalDeclarationSnapshotData`
 * calculé à la volée par le controller via `DeclarationFiscalEngine`
 * (D5.2). Les opt-outs Requalified (D5.1) sont déjà appliqués au
 * snapshot, donc les montants affichés correspondent à ce que le PDF
 * généré contiendra.
 *
 * Utilisée dans la page Review (prévisualisation avant génération) et
 * la page Show (snapshot recalculé).
 */
import { Calculator } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEuros } from '@/Utils/format/formatEuros';

const props = defineProps<{
    snapshot: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
}>();

const hasVehicles = computed<boolean>(() => props.snapshot.vehicleBreakdown.length > 0);
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
                    <dd class="font-medium text-slate-900 tabular-nums">{{ formatEuros(snapshot.co2DueTotal) }}</dd>
                </div>
                <div class="flex items-baseline justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-600">Taxe polluants atmosphériques (CIBS L. 421-58)</dt>
                    <dd class="font-medium text-slate-900 tabular-nums">{{ formatEuros(snapshot.pollutantsDueTotal) }}</dd>
                </div>
                <div class="flex items-baseline justify-between pt-1">
                    <dt class="text-base font-semibold text-slate-900">Total dû</dt>
                    <dd class="text-lg font-semibold text-slate-900 tabular-nums">{{ formatEuros(snapshot.totalDue) }}</dd>
                </div>
            </dl>

            <div>
                <h3 class="mb-2 text-xs font-medium tracking-wider text-slate-500 uppercase">
                    Détail par véhicule
                </h3>
                <div v-if="!hasVehicles" class="rounded-lg bg-slate-50 px-4 py-3 text-sm italic text-slate-500">
                    Aucun véhicule attribué sur cet exercice.
                </div>
                <div v-else class="overflow-hidden rounded-lg border border-slate-200">
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
                                v-for="row in snapshot.vehicleBreakdown"
                                :key="row.vehicleId"
                                class="text-slate-700"
                            >
                                <td class="px-3 py-2">{{ row.vehicleLabel }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ row.daysAssigned }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatEuros(row.co2Due) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatEuros(row.pollutantsDue) }}</td>
                                <td class="px-3 py-2 text-right font-medium text-slate-900 tabular-nums">{{ formatEuros(row.totalDue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </Card>
</template>
