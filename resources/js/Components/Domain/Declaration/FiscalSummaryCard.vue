<script setup lang="ts">
/**
 * Carte de synthèse fiscale d'une déclaration (Phase 11 D5.6, refondu
 * D5.8.3 : breakdown chronologique par contrat avec clusters in-line).
 *
 * Affiche :
 *   1. La synthèse 3 lignes (CO2, polluants, total dû) recalculée
 *      à la volée par `DeclarationFiscalEngine` (D5.2) ou figée
 *      depuis le payload persisté en BDD (D5.7.5).
 *   2. Le tableau chronologique des contrats via
 *      `<DeclarationContractList>` (D5.8.3) : tri pré-calculé côté
 *      backend `(vehicleLabel, startDate)`, groupage visuel des
 *      clusters LCD à risque.
 *
 * Mode interactif (Review) : passer `reviewClusters` + écouter
 * `submit` pour appeler `useReviewForm`.
 * Mode passif (Show) : ne pas passer `reviewClusters`.
 */
import { Calculator } from 'lucide-vue-next';
import { ref } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';
import DeclarationContractList from './DeclarationContractList.vue';

const props = defineProps<{
    snapshot: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
    reviewClusters?: App.Data.User.FiscalDeclaration.ReviewClusterData[];
    submitting?: boolean;
}>();

const emit = defineEmits<{
    submit: [
        cluster: App.Data.User.FiscalDeclaration.ReviewClusterData,
        decision: 'conserved' | 'requalified',
        justification: string | null,
    ];
}>();

const contractListRef = ref<InstanceType<typeof DeclarationContractList> | null>(null);

defineExpose({
    scrollToCluster(fingerprint: string): void {
        contractListRef.value?.scrollToCluster(fingerprint);
    },
});
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
                    <dd class="font-medium text-slate-900 tabular-nums">
                        {{ formatEur(snapshot.co2DueTotal, 2) }}
                    </dd>
                </div>
                <div class="flex items-baseline justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-600">
                        Taxe polluants atmosphériques (CIBS L. 421-58)
                    </dt>
                    <dd class="font-medium text-slate-900 tabular-nums">
                        {{ formatEur(snapshot.pollutantsDueTotal, 2) }}
                    </dd>
                </div>
                <div class="flex items-baseline justify-between pt-1">
                    <dt class="text-base font-semibold text-slate-900">Total dû</dt>
                    <dd class="text-lg font-semibold text-slate-900 tabular-nums">
                        {{ formatEur(snapshot.totalDue, 2) }}
                    </dd>
                </div>
            </dl>

            <div>
                <h3 class="mb-2 text-xs font-medium tracking-wider text-slate-500 uppercase">
                    Détail chronologique par contrat
                </h3>
                <DeclarationContractList
                    ref="contractListRef"
                    :contract-breakdown="props.snapshot.contractBreakdown"
                    :review-clusters="props.reviewClusters"
                    :submitting="props.submitting"
                    @submit="(cluster, decision, justification) => emit('submit', cluster, decision, justification)"
                />
            </div>
        </div>
    </Card>
</template>
