<script setup lang="ts">
/**
 * Liste historique des décisions de revue appliquées au calcul fiscal
 * (Phase 11 D5.6). Remplace le placeholder D4
 * `Show/partials/ClustersHistorySection.vue`.
 *
 * Affiche un tableau récapitulant chaque décision matchée par
 * fingerprint au moment du calcul du snapshot : code de risque,
 * decision (Requalifié / Conservé), nombre de contrats du cluster,
 * justification.
 *
 * Les décisions Requalifié sont mises en valeur (pill rose) car elles
 * impactent réellement les montants : leurs contrats sont retirés de
 * l'exonération R-2024-021 (LCD).
 */
import { ListTree } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import type { StatusTone } from '@/types/ui';

const props = defineProps<{
    decisions: Array<App.Data.User.FiscalDeclaration.AppliedDecisionEntryData>;
    optOutContractsCount: number;
}>();

const hasDecisions = computed<boolean>(() => props.decisions.length > 0);

function decisionTone(decision: 'conserved' | 'requalified'): StatusTone {
    return decision === 'requalified' ? 'rose' : 'emerald';
}

function decisionLabel(decision: 'conserved' | 'requalified'): string {
    return decision === 'requalified' ? 'Requalifié' : 'Conservé';
}

function riskCodeLabel(code: 'R-LCD-CHAIN' | 'R-LCD-CHAIN-FORT'): string {
    return code === 'R-LCD-CHAIN-FORT' ? 'Chaîne LCD forte' : 'Chaîne LCD';
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center gap-2">
                <ListTree :size="18" :stroke-width="1.75" class="text-slate-500" />
                <h2 class="text-base font-semibold text-slate-900">Décisions de revue appliquées</h2>
            </div>
        </template>

        <div v-if="!hasDecisions" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            Aucune chaîne LCD à risque détectée sur cet exercice.
        </div>

        <div v-else class="flex flex-col gap-4">
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-medium tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Cluster</th>
                            <th class="px-3 py-2 text-left font-medium">Risque</th>
                            <th class="px-3 py-2 text-left font-medium">Décision</th>
                            <th class="px-3 py-2 text-right font-medium">Contrats</th>
                            <th class="px-3 py-2 text-left font-medium">Justification</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in decisions" :key="row.clusterFingerprint" class="text-slate-700">
                            <td class="px-3 py-2 font-mono text-xs text-slate-500">
                                {{ row.clusterFingerprint.slice(0, 12) }}…
                            </td>
                            <td class="px-3 py-2">{{ riskCodeLabel(row.riskCode) }}</td>
                            <td class="px-3 py-2">
                                <StatusPill :tone="decisionTone(row.decision)">
                                    {{ decisionLabel(row.decision) }}
                                </StatusPill>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ row.contractIds.length }}</td>
                            <td class="px-3 py-2 text-slate-600">
                                <span v-if="row.justification">{{ row.justification }}</span>
                                <span v-else class="italic text-slate-400">·</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="optOutContractsCount > 0" class="text-xs text-slate-500">
                {{ optOutContractsCount }} contrat<span v-if="optOutContractsCount > 1">s</span>
                requalifié<span v-if="optOutContractsCount > 1">s</span> retiré<span v-if="optOutContractsCount > 1">s</span>
                de l'exonération R-2024-021 (locations courtes durées).
            </p>
        </div>
    </Card>
</template>
