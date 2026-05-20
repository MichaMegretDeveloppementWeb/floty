<script setup lang="ts">
/**
 * Chronological contract table of a declaration with visual cluster grouping.
 * Iterates contractBreakdown (sorted backend-side), groups same-fingerprint
 * contracts together with local reordering for interleaved off-cluster rows.
 * Interactive mode (reviewClusters provided) exposes a per-cluster Decide button.
 */
import { computed, ref } from 'vue';
import ClusterDecisionModal from './ClusterDecisionModal.vue';
import ClusterGroup from './ClusterGroup.vue';
import ContractRow from './ContractRow.vue';

type Contract = App.Data.User.FiscalDeclaration.ContractSnapshotEntryData;
type ReviewCluster = App.Data.User.FiscalDeclaration.ReviewClusterData;

type Group =
    | { kind: 'cluster'; fingerprint: string; contracts: Contract[] }
    | { kind: 'single'; contract: Contract };

const props = defineProps<{
    contractBreakdown: Contract[];
    /** Clusters provided on the Review page to enable the decision modal. Omitted on Show. */
    reviewClusters?: ReviewCluster[];
    submitting?: boolean;
    /** Configurable thresholds, required in Review mode. */
    riskSettings?: App.Data.User.FiscalRiskSettings.FiscalRiskSettingsData;
}>();

const emit = defineEmits<{
    submit: [
        cluster: ReviewCluster,
        decision: 'conserved' | 'requalified',
        justification: string | null,
        excludedContractIds: number[],
    ];
}>();

// 7 columns: Period, Type, Vehicle, Days, CO2, Pollutants, Tax.
const COLSPAN = 7;
const CLUSTER_ROW_BG = 'bg-slate-50';

/** Border accent class propagated to ContractRow children of a cluster. */
function accentBorderClassFor(
    level: App.Enums.FiscalReviewDecision.RiskLevel,
): string {
    return level === 'eleve'
        ? 'border-l-2 border-l-rose-400'
        : 'border-l-2 border-l-amber-400';
}

const isInteractive = computed<boolean>(() => props.reviewClusters !== undefined);

const groups = computed<Group[]>(() => {
    // Render same-cluster contracts as one contiguous block at the position
    // of the first one; interleaved off-cluster rows fall after the block.
    const byFingerprint = new Map<string, Contract[]>();

    for (const c of props.contractBreakdown) {
        if (c.clusterFingerprint !== null) {
            const arr = byFingerprint.get(c.clusterFingerprint) ?? [];
            arr.push(c);
            byFingerprint.set(c.clusterFingerprint, arr);
        }
    }

    const result: Group[] = [];
    const rendered = new Set<string>();

    for (const contract of props.contractBreakdown) {
        const fp = contract.clusterFingerprint;

        if (fp === null) {
            result.push({ kind: 'single', contract });

            continue;
        }

        if (rendered.has(fp)) {
            continue;
        }

        const contracts = byFingerprint.get(fp) ?? [contract];
        result.push({ kind: 'cluster', fingerprint: fp, contracts });
        rendered.add(fp);
    }

    return result;
});

/** Rich Review-cluster lookup; falls back to data derived from the contracts on Show. */
const reviewClusterByFingerprint = computed<Map<string, ReviewCluster>>(() => {
    const map = new Map<string, ReviewCluster>();

    for (const cluster of props.reviewClusters ?? []) {
        map.set(cluster.fingerprint, cluster);
    }

    return map;
});

interface ClusterMeta {
    riskCode: App.Enums.FiscalReviewDecision.RiskCode;
    riskLevel: App.Enums.FiscalReviewDecision.RiskLevel;
    contractsCount: number;
    coveragePeriodDays: number;
    coverageStartDate: string;
    coverageEndDate: string;
    distinctVehiclesCount: number;
    decision: App.Enums.FiscalReviewDecision.ReviewDecisionType | null;
    justification: string | null;
}

function metaFromCluster(
    fingerprint: string,
    contracts: Contract[],
): ClusterMeta | null {
    const cluster = reviewClusterByFingerprint.value.get(fingerprint);

    if (cluster) {
        return {
            riskCode: cluster.code,
            riskLevel: cluster.level,
            contractsCount: cluster.contractsCount,
            coveragePeriodDays: cluster.coveragePeriodDays,
            coverageStartDate: cluster.coverageStartDate,
            coverageEndDate: cluster.coverageEndDate,
            distinctVehiclesCount: cluster.distinctVehiclesCount,
            decision: cluster.decision,
            justification: cluster.justification,
        };
    }

    // Show fallback: derive metadata from the contracts (no Review cluster available).
    const firstContract = contracts[0];

    if (firstContract === undefined) {
        return null;
    }

    if (firstContract.clusterRiskCode === null || firstContract.clusterRiskLevel === null) {
        return null;
    }

    const startDates = contracts.map((c) => c.startDate).sort();
    const endDates = contracts.map((c) => c.endDate).sort();
    const minStart = startDates[0] ?? firstContract.startDate;
    const maxEnd = endDates[endDates.length - 1] ?? firstContract.endDate;

    const minStartDate = new Date(minStart);
    const maxEndDate = new Date(maxEnd);
    const dayMs = 24 * 60 * 60 * 1000;
    const coveragePeriodDays = Math.max(
        1,
        Math.round((maxEndDate.getTime() - minStartDate.getTime()) / dayMs) + 1,
    );

    const distinctVehiclesCount = new Set(contracts.map((c) => c.vehicleId)).size;

    return {
        riskCode: firstContract.clusterRiskCode,
        riskLevel: firstContract.clusterRiskLevel,
        contractsCount: contracts.length,
        coveragePeriodDays,
        coverageStartDate: minStart,
        coverageEndDate: maxEnd,
        distinctVehiclesCount,
        decision: firstContract.clusterDecision,
        justification: firstContract.clusterJustification,
    };
}

// Shared decision modal state; only the active cluster is passed in when open.
const modalOpen = ref<boolean>(false);
const selectedCluster = ref<ReviewCluster | null>(null);

function openModalFor(cluster: ReviewCluster): void {
    selectedCluster.value = cluster;
    modalOpen.value = true;
}

function handleModalSubmit(
    decision: 'conserved' | 'requalified',
    justification: string | null,
    excludedContractIds: number[],
): void {
    if (selectedCluster.value !== null) {
        emit('submit', selectedCluster.value, decision, justification, excludedContractIds);
    }

    modalOpen.value = false;
}

defineExpose({
    /** Scrolls the page to the target cluster header. */
    scrollToCluster(fingerprint: string): void {
        const el = document.getElementById(`cluster-${fingerprint}`);

        if (el !== null) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    },
});
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full min-w-[820px] text-sm">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">Période</th>
                    <th class="px-3 py-2 text-left font-medium">Type</th>
                    <th class="px-3 py-2 text-left font-medium">Véhicule</th>
                    <th class="px-3 py-2 text-right font-medium">Jours</th>
                    <th class="px-3 py-2 text-right font-medium">CO₂</th>
                    <th class="px-3 py-2 text-right font-medium">Polluants</th>
                    <th class="px-3 py-2 text-right font-medium">Taxe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <template v-for="(group, idx) in groups" :key="idx">
                    <ContractRow
                        v-if="group.kind === 'single'"
                        :contract="group.contract"
                    />
                    <template v-else>
                        <template v-if="metaFromCluster(group.fingerprint, group.contracts)">
                            <ClusterGroup
                                :cluster-id="`cluster-${group.fingerprint}`"
                                :risk-code="metaFromCluster(group.fingerprint, group.contracts)!.riskCode"
                                :risk-level="metaFromCluster(group.fingerprint, group.contracts)!.riskLevel"
                                :contracts-count="metaFromCluster(group.fingerprint, group.contracts)!.contractsCount"
                                :coverage-period-days="metaFromCluster(group.fingerprint, group.contracts)!.coveragePeriodDays"
                                :coverage-start-date="metaFromCluster(group.fingerprint, group.contracts)!.coverageStartDate"
                                :coverage-end-date="metaFromCluster(group.fingerprint, group.contracts)!.coverageEndDate"
                                :distinct-vehicles-count="metaFromCluster(group.fingerprint, group.contracts)!.distinctVehiclesCount"
                                :colspan="COLSPAN"
                                :decision="metaFromCluster(group.fingerprint, group.contracts)!.decision"
                                :interactive="isInteractive && reviewClusterByFingerprint.has(group.fingerprint)"
                                :justification="metaFromCluster(group.fingerprint, group.contracts)!.justification"
                                @edit-decision="openModalFor(reviewClusterByFingerprint.get(group.fingerprint)!)"
                            >
                                <ContractRow
                                    v-for="contract in group.contracts"
                                    :key="contract.contractId"
                                    :contract="contract"
                                    :bg-class="CLUSTER_ROW_BG"
                                    :accent-border-class="accentBorderClassFor(
                                        metaFromCluster(group.fingerprint, group.contracts)!.riskLevel,
                                    )"
                                />
                            </ClusterGroup>
                        </template>
                        <template v-else>
                            <ContractRow
                                v-for="contract in group.contracts"
                                :key="contract.contractId"
                                :contract="contract"
                            />
                        </template>
                    </template>
                </template>

                <tr v-if="contractBreakdown.length === 0">
                    <td :colspan="COLSPAN" class="px-3 py-4 text-center text-sm italic text-slate-500">
                        Aucun véhicule attribué sur cet exercice.
                    </td>
                </tr>
            </tbody>
        </table>

        <ClusterDecisionModal
            v-if="isInteractive && riskSettings"
            v-model:open="modalOpen"
            :cluster="selectedCluster"
            :submitting="props.submitting ?? false"
            :risk-settings="riskSettings"
            @submit="handleModalSubmit"
        />
    </div>
</template>
