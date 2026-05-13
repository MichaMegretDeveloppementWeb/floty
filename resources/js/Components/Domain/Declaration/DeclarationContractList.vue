<script setup lang="ts">
/**
 * Table chronologique des contrats d'une déclaration fiscale avec
 * groupage visuel par cluster LCD à risque (Phase 11 D5.8, refondu
 * D5.9.C avec modale de décision, refondu D5.10.N · critère plage
 * couverte + tri snapshot strictement chronologique, refondu
 * D5.10.Q · réorganisation locale pour clusters intercalés).
 *
 * Itère sur `contractBreakdown` (déjà trié backend par
 * `(startDate, vehicleId, contractId)` ASC depuis D5.10.N), regroupe
 * les contrats appartenant à un même `clusterFingerprint` dans une
 * boîte visuelle (composant `<ClusterGroup>`). Les contrats sans
 * cluster sont rendus comme des `<ContractRow>` isolées.
 *
 * **Réorganisation locale (D5.10.Q)** · les contrats d'un même cluster
 * sont rendus physiquement collés (header + N rows + footer en bloc)
 * à la position du premier contrat du cluster dans le tri reçu. Si un
 * contrat hors-cluster (LLD intercalé sur autre véhicule) sépare
 * temporellement deux contrats du cluster, il est déplacé après le
 * bloc cluster · cohérence visuelle prime sur ordre chronologique
 * strict global. Le tri backend reste inchangé · seul le rendu Vue
 * réorganise.
 *
 * **Mode interactif** · quand `reviewClusters` est fourni (page
 * Review), un bouton « Décider » apparaît dans le header de chaque
 * cluster et ouvre `<ClusterDecisionModal>`. À la soumission, l'event
 * `submit(cluster, decision, justification)` est ré-émis au parent.
 *
 * **Mode passif** (page Show) · `reviewClusters` est `undefined`,
 * aucune modale n'est montée. Les ClusterGroup affichent la décision
 * déjà prise (badge dans le header + justification persistée en
 * dessous).
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
    /** Clusters fournis en page Review pour activer la modale de décision. Absent en page Show (mode passif). */
    reviewClusters?: ReviewCluster[];
    submitting?: boolean;
}>();

const emit = defineEmits<{
    submit: [
        cluster: ReviewCluster,
        decision: 'conserved' | 'requalified',
        justification: string | null,
    ];
}>();

// Phase 13 D5.10.H · 7 colonnes (Période, Type, Véhicule, Jours, CO₂,
// Polluants, Taxe totale). Le colspan est propagé au header/footer de
// `<ClusterGroup>`.
const COLSPAN = 7;
const CLUSTER_ROW_BG = 'bg-slate-50';

/**
 * Calcule la classe d'accent (`border-l-2` + couleur) à propager aux
 * `<ContractRow>` enfants d'un cluster (Phase 13 D5.10.C). Cohérent
 * avec la couleur appliquée par `<ClusterGroup>` sur son header et sa
 * row de fermeture · le résultat visuel est une bordure verticale
 * continue du haut au bas du cluster.
 */
function accentBorderClassFor(
    level: App.Enums.FiscalReviewDecision.RiskLevel,
): string {
    return level === 'eleve'
        ? 'border-l-2 border-l-rose-400'
        : 'border-l-2 border-l-amber-400';
}

const isInteractive = computed<boolean>(() => props.reviewClusters !== undefined);

const groups = computed<Group[]>(() => {
    // Phase 13 D5.10.Q · réorganisation locale · les contrats d'un
    // même cluster sont rendus collés en bloc (header + N rows + footer)
    // à la position du premier contrat du cluster dans le tri reçu.
    // Les contrats hors-cluster intercalés sont rendus normalement
    // à leur position (donc APRÈS le bloc cluster s'ils étaient
    // chronologiquement entre deux contrats du cluster).

    // Index par fingerprint pour résoudre en O(1).
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
            // Déjà rendu en bloc lors de la 1ère rencontre · skip.
            continue;
        }

        const contracts = byFingerprint.get(fp) ?? [contract];
        result.push({ kind: 'cluster', fingerprint: fp, contracts });
        rendered.add(fp);
    }

    return result;
});

/**
 * Lookup riche du cluster côté Review (contractsCount, plage couverte,
 * nb véhicules distincts, decision pré-appliquée, justification
 * éditable, level). Quand le cluster n'existe pas dans
 * `reviewClusters` (par exemple en page Show), on dérive les
 * méta-données minimales depuis les contrats eux-mêmes (decision
 * persistée portée par `contract.clusterDecision`).
 */
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

    // Fallback Show · pas de cluster Review (snapshot persisté seul).
    // On déduit les méta-données depuis les contrats eux-mêmes ·
    // contractsCount = nombre de rows du groupe local · plage couverte
    // dérivée des dates min/max (déjà tri chronologique strict) ·
    // distinctVehiclesCount par dédoublonnage.
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

/**
 * État de la modale de décision (Phase 12 D5.9.C). La modale est
 * partagée entre tous les clusters interactifs · seul le cluster
 * sélectionné est passé en prop quand ouverte.
 */
const modalOpen = ref<boolean>(false);
const selectedCluster = ref<ReviewCluster | null>(null);

function openModalFor(cluster: ReviewCluster): void {
    selectedCluster.value = cluster;
    modalOpen.value = true;
}

function handleModalSubmit(
    decision: 'conserved' | 'requalified',
    justification: string | null,
): void {
    if (selectedCluster.value !== null) {
        emit('submit', selectedCluster.value, decision, justification);
    }

    modalOpen.value = false;
}

defineExpose({
    /**
     * Permet à `<DeclarationClustersRecap>` de faire défiler la page
     * jusqu'au cluster ciblé. Phase 13 D5.10.P · `block: 'center'`
     * pour éviter que le header de cluster soit masqué par le recap
     * sticky positionné en haut du viewport.
     */
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
            v-if="isInteractive"
            v-model:open="modalOpen"
            :cluster="selectedCluster"
            :submitting="props.submitting ?? false"
            @submit="handleModalSubmit"
        />
    </div>
</template>
