<script setup lang="ts">
/**
 * Fiscal summary card for a declaration.
 * Renders the 3-row summary (CO2, pollutants, total) plus a chronological
 * contract breakdown via DeclarationContractList. Interactive mode (Review)
 * passes reviewClusters and listens to submit; passive mode (Show) omits them.
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
    /** Configurable risk thresholds. Required in Review mode. */
    riskSettings?: App.Data.User.FiscalRiskSettings.FiscalRiskSettingsData;
}>();

const emit = defineEmits<{
    submit: [
        cluster: App.Data.User.FiscalDeclaration.ReviewClusterData,
        decision: 'conserved' | 'requalified',
        justification: string | null,
        excludedContractIds: number[],
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
                    <dt class="text-base font-semibold text-slate-900">Total à déclarer</dt>
                    <dd class="text-lg font-semibold text-slate-900 tabular-nums">
                        {{ formatEur(snapshot.totalDue, 0) }}
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
                    :risk-settings="props.riskSettings"
                    @submit="(cluster, decision, justification, excludedIds) => emit('submit', cluster, decision, justification, excludedIds)"
                />
            </div>
        </div>
    </Card>
</template>
